<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AccessGroupController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\EquipmentTransferController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\TaskController;

Route::get('/',[TaskController::class, 'index'])->middleware(['auth', 'verified']);
Route::get('/tasks/create',[TaskController::class, 'create'])->middleware(['auth', 'verified', 'can:tarefas.criar']);
Route::get('/tasks/{id}', [TaskController::class, 'show'])->middleware(['auth', 'verified']);
Route::get('/tasks/{id}/imagem', [TaskController::class, 'imagem'])->middleware(['auth', 'verified'])->name('tasks.image');
Route::post('/tasks', [TaskController::class, 'store'])->middleware(['auth', 'verified', 'can:tarefas.criar', 'throttle:20,1']);
Route::delete('/tasks/{id}', [TaskController::class, 'destroy'])->middleware(['auth', 'verified']);
Route::get('/tasks/edit/{id}', [TaskController::class, 'edit'])->middleware(['auth', 'verified']);
Route::put('/tasks/update/{id}', [TaskController::class, 'update'])->middleware(['auth', 'verified']);

Route::get('/contact', function () {
    return view('contact');
});

Route::get('/dashboard', [TaskController::class, 'dashboard'])->middleware(['auth', 'verified']);

Route::post('/tasks/join/{id}', [TaskController::class, 'joinTask'])->middleware(['auth', 'verified', 'can:tarefas.assumir']);
Route::post('/tasks/leave/{id}', [TaskController::class, 'sair'])->middleware(['auth', 'verified'])->name('tasks.leave');

// Painel de indicadores (para quem acompanha todas as tarefas)
Route::get('/painel', [PainelController::class, 'index'])->middleware(['auth', 'verified', 'can:painel.ver'])->name('painel');

// Inventário de equipamentos
Route::middleware(['auth', 'verified'])->prefix('equipamentos')->name('equipamentos.')->group(function () {
    // Importação (as rotas fixas vêm antes de /{equipamento})
    Route::middleware('can:equipamentos.importar')->group(function () {
        Route::get('/importar', [EquipmentTransferController::class, 'form'])->name('import.form');
        Route::post('/importar', [EquipmentTransferController::class, 'importar'])->name('import.store');
        Route::get('/modelo', [EquipmentTransferController::class, 'modelo'])->name('template');
    });

    Route::middleware('can:equipamentos.gerenciar')->group(function () {
        Route::get('/novo', [EquipmentController::class, 'create'])->name('create');
        Route::post('/', [EquipmentController::class, 'store'])->name('store');
        Route::get('/{equipamento}/editar', [EquipmentController::class, 'edit'])->name('edit');
        Route::put('/{equipamento}', [EquipmentController::class, 'update'])->name('update');
    });

    Route::middleware('can:equipamentos.ver')->group(function () {
        Route::get('/exportar', [EquipmentTransferController::class, 'exportar'])->name('export');
        Route::get('/', [EquipmentController::class, 'index'])->name('index');
        Route::get('/{equipamento}', [EquipmentController::class, 'show'])->name('show');
    });
});

// Administração: usuários e grupos de acesso
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('can:usuarios.gerenciar')->group(function () {
        Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
        Route::get('/usuarios/novo', [UserController::class, 'create'])->name('usuarios.create');
        Route::post('/usuarios', [UserController::class, 'store'])->name('usuarios.store');
        Route::get('/usuarios/{usuario}/editar', [UserController::class, 'edit'])->name('usuarios.edit');
        Route::put('/usuarios/{usuario}', [UserController::class, 'update'])->name('usuarios.update');
    });

    Route::middleware('can:grupos.gerenciar')->group(function () {
        Route::get('/grupos', [AccessGroupController::class, 'index'])->name('grupos.index');
        Route::get('/grupos/novo', [AccessGroupController::class, 'create'])->name('grupos.create');
        Route::post('/grupos', [AccessGroupController::class, 'store'])->name('grupos.store');
        Route::get('/grupos/{grupo}/editar', [AccessGroupController::class, 'edit'])->name('grupos.edit');
        Route::put('/grupos/{grupo}', [AccessGroupController::class, 'update'])->name('grupos.update');
        Route::delete('/grupos/{grupo}', [AccessGroupController::class, 'destroy'])->name('grupos.destroy');
    });
});




