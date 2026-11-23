<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AccessGroupController;
use App\Http\Controllers\Admin\CadastroController;
use App\Http\Controllers\Admin\TaskTemplateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AceiteTermosController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\EquipmentTransferController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\TaskAttachmentController;
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

    // Chat interno (conversas individuais e grupos)
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/nao-lidas', [ChatController::class, 'naoLidas'])->name('chat.nao-lidas');
    Route::post('/chat/individual', [ChatController::class, 'individual'])->name('chat.individual');
    Route::post('/chat/grupos', [ChatController::class, 'criarGrupo'])->name('chat.grupos.store');
    Route::get('/chat/{conversa}', [ChatController::class, 'show'])->whereNumber('conversa')->name('chat.show');
    Route::put('/chat/{conversa}/grupo', [ChatController::class, 'atualizarGrupo'])->whereNumber('conversa')->name('chat.grupos.update');
    Route::post('/chat/{conversa}/sair', [ChatController::class, 'sair'])->whereNumber('conversa')->name('chat.sair');
    Route::post('/chat/{conversa}/mensagens', [ChatController::class, 'enviar'])->whereNumber('conversa')->middleware('throttle:60,1')->name('chat.mensagens.store');
    Route::get('/chat/{conversa}/mensagens', [ChatController::class, 'novas'])->whereNumber('conversa')->name('chat.mensagens.index');
    Route::get('/chat/{conversa}/anexos/{mensagem}', [ChatController::class, 'anexo'])->whereNumber('conversa')->name('chat.anexo');

    // Anexos (imagens e PDF): só quem pode ver a tarefa abre e envia
    Route::get('/tasks/{id}/anexos/{anexo}', [TaskAttachmentController::class, 'show'])->whereNumber('id')->name('tasks.attachments.show');
    Route::post('/tasks/{id}/anexos', [TaskAttachmentController::class, 'store'])->whereNumber('id')->middleware('throttle:20,1')->name('tasks.attachments.store');
    Route::delete('/tasks/{id}/anexos/{anexo}', [TaskAttachmentController::class, 'destroy'])->whereNumber('id')->name('tasks.attachments.destroy');
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

    // Modelos de tarefa (formulários prontos, ex.: Devolução de valores)
    Route::middleware('can:modelos.gerenciar')->group(function () {
        Route::get('/modelos', [TaskTemplateController::class, 'index'])->name('modelos.index');
        Route::get('/modelos/novo', [TaskTemplateController::class, 'create'])->name('modelos.create');
        Route::post('/modelos', [TaskTemplateController::class, 'store'])->name('modelos.store');
        Route::get('/modelos/{modelo}/editar', [TaskTemplateController::class, 'edit'])->name('modelos.edit');
        Route::put('/modelos/{modelo}', [TaskTemplateController::class, 'update'])->name('modelos.update');
        Route::delete('/modelos/{modelo}', [TaskTemplateController::class, 'destroy'])->name('modelos.destroy');
    });

    // Setores e lojas (usados em usuários e equipamentos)
    Route::middleware('can:cadastros.gerenciar')->prefix('cadastros/{tipo}')->where(['tipo' => 'setores|lojas'])
        ->name('cadastros.')->group(function () {
            Route::get('/', [CadastroController::class, 'index'])->name('index');
            Route::post('/', [CadastroController::class, 'store'])->name('store');
            Route::put('/{id}', [CadastroController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [CadastroController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });
});

// LGPD: aceite dos Termos de Uso e da Política de Privacidade.
// Só "auth" (sem "verified"): quem ainda não confirmou o e-mail também pode aceitar.
Route::middleware('auth')->prefix('aceite-dos-termos')->name('termos.')->group(function () {
    Route::get('/', [AceiteTermosController::class, 'show'])->name('aceite');
    Route::post('/', [AceiteTermosController::class, 'store'])->middleware('throttle:10,1')->name('aceitar');
    Route::post('/recusar', [AceiteTermosController::class, 'recusar'])->name('recusar');
});
