<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\NotificadorTarefas;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Comentários e autorizações na linha do tempo da tarefa.
 *
 * - Todos que podem ver a tarefa podem comentar.
 * - "Autorizo" / "Não autorizo" só podem ser registrados por participantes
 *   ou por quem tem "tarefas.gerenciar_todas".
 */
class TaskCommentController extends Controller
{
    public function __construct(private NotificadorTarefas $notificador) {}

    public function store(Request $request, $id)
    {
        $task = Task::with(['user', 'users', 'participantes'])->findOrFail($id);
        $usuario = $request->user();

        abort_unless($task->podeComentar($usuario), 404);

        $dados = $request->validate([
            'tipo' => ['nullable', Rule::in(['comentario', 'autorizacao', 'recusa'])],
            'corpo' => ['required', 'string', 'max:3000'],
        ], [
            'corpo.required' => 'Escreva o comentário antes de enviar.',
            'corpo.max' => 'O comentário pode ter no máximo 3000 caracteres.',
        ]);

        $tipo = $dados['tipo'] ?? 'comentario';

        if ($tipo !== 'comentario') {
            abort_unless($task->podeAutorizar($usuario), 403);
        }

        $atividade = $task->registrar($tipo, $usuario, trim($dados['corpo']));
        $this->notificador->mensagem($task, $atividade, $usuario);

        $mensagens = [
            'comentario' => 'Comentário enviado.',
            'autorizacao' => 'Autorização registrada.',
            'recusa' => 'Recusa registrada.',
        ];

        return redirect('/tasks/'.$task->id.'#linha-do-tempo')->with('msg', $mensagens[$tipo]);
    }
}
