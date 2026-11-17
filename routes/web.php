<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AccessGroupController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\EquipmentTransferController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskParticipantController;
use App\Http\Controllers\TaskStatusController;

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

Route::get('/dashboard', [TaskController::class, 'dashboard'])->middleware(['auth', 'verified'])->name('dashboard');

Route::post('/tasks/join/{id}', [TaskController::class, 'joinTask'])->middleware(['auth', 'verified', 'can:tarefas.assumir']);
Route::post('/tasks/leave/{id}', [TaskController::class, 'sair'])->middleware(['auth', 'verified'])->name('tasks.leave');

// Situação, comentários e participantes (as permissões são conferidas nos controllers,
// porque dependem do papel da pessoa em cada tarefa: quem abriu, responsável ou participante)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/tasks/{id}/status', [TaskStatusController::class, 'update'])->name('tasks.status');
    Route::post('/tasks/{id}/comentarios', [TaskCommentController::class, 'store'])->middleware('throttle:30,1')->name('tasks.comments.store');
    Route::post('/tasks/{id}/participantes', [TaskParticipantController::class, 'store'])->name('tasks.participants.store');
    Route::delete('/tasks/{id}/participantes/{userId}', [TaskParticipantController::class, 'destroy'])->name('tasks.participants.destroy');
});

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

    // Lixeira (as rotas fixas vêm antes de /{equipamento})
    Route::middleware('can:equipamentos.excluir')->group(function () {
        Route::get('/lixeira', [EquipmentController::class, 'lixeira'])->name('lixeira');
        Route::post('/{id}/restaurar', [EquipmentController::class, 'restaurar'])->whereNumber('id')->name('restore');
        Route::delete('/{equipamento}', [EquipmentController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('can:equipamentos.ver')->group(function () {
        // Comentários: quem vê o inventário pode comentar; apagar é conferido no controller
        Route::post('/{equipamento}/comentarios', [EquipmentController::class, 'comentar'])->middleware('throttle:30,1')->name('comments.store');
        Route::delete('/comentarios/{comentario}', [EquipmentController::class, 'apagarComentario'])->name('comments.destroy');

        Route::get('/exportar', [EquipmentTransferController::class, 'exportar'])->name('export');
        Route::get('/', [EquipmentController::class, 'index'])->name('index');
        Route::get('/{equipamento}', [EquipmentController::class, 'show'])->name('show');
        Route::get('/{equipamento}/etiqueta', [EquipmentController::class, 'etiqueta'])->name('label');
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
        Route::post('/usuarios/{usuario}/desativar', [UserController::class, 'desativar'])->name('usuarios.deactivate');
        Route::post('/usuarios/{usuario}/ativar', [UserController::class, 'ativar'])->name('usuarios.activate');
        Route::post('/usuarios/{usuario}/redefinir-senha', [UserController::class, 'enviarRedefinicaoDeSenha'])->middleware('throttle:6,1')->name('usuarios.password-link');
        Route::post('/usuarios/{usuario}/remover-2fa', [UserController::class, 'removerDoisFatores'])->name('usuarios.remove-2fa');
        Route::post('/usuarios/{usuario}/confirmar-email', [UserController::class, 'confirmarEmail'])->name('usuarios.verify-email');
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




