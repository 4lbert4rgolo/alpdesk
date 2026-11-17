<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Services\NotificadorTarefas;
use Illuminate\Http\Request;

/**
 * Participantes: pessoas que acompanham a tarefa, comentam e autorizam.
 *
 * - Adicionar e remover: quem abriu, responsáveis ou quem gerencia todas.
 * - O próprio participante pode sair a qualquer momento.
 * - Ser participante NÃO dá permissão para editar, excluir ou atender a tarefa,
 *   nem para ver outras tarefas.
 */
class TaskParticipantController extends Controller
{
    public function __construct(private NotificadorTarefas $notificador) {}

    public function store(Request $request, $id)
    {
        $task = Task::with(['user', 'users', 'participantes'])->findOrFail($id);
        $usuario = $request->user();

        abort_unless($task->visivelPara($usuario), 404);
        abort_unless($task->podeGerenciarParticipantes($usuario), 403);

        $dados = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ], [
            'user_id.required' => 'Escolha uma pessoa da lista.',
            'user_id.exists' => 'Escolha uma pessoa da lista.',
        ]);

        $participante = User::findOrFail($dados['user_id']);

        if ($task->envolvidos()->contains('id', $participante->id)) {
            return redirect('/tasks/'.$task->id)->with('msg', $participante->name.' já está nesta tarefa.');
        }

        $task->participantes()->attach($participante->id, ['added_by' => $usuario->id]);
        $task->registrar('participante_adicionado', $usuario, null, [
            'user_id' => $participante->id,
            'nome' => $participante->name,
        ]);

        $this->notificador->participanteAdicionado($task, $participante, $usuario);

        return redirect('/tasks/'.$task->id)->with('msg', $participante->name.' agora participa da tarefa.');
    }

    public function destroy(Request $request, $id, $userId)
    {
        $task = Task::with(['user', 'users', 'participantes'])->findOrFail($id);
        $usuario = $request->user();

        abort_unless($task->visivelPara($usuario), 404);

        $participante = $task->participantes->firstWhere('id', (int) $userId);
        abort_unless($participante, 404);

        $proprio = (int) $participante->id === (int) $usuario->id;
        abort_unless($proprio || $task->podeGerenciarParticipantes($usuario), 403);

        $task->participantes()->detach($participante->id);
        $task->registrar('participante_removido', $usuario, null, [
            'user_id' => $participante->id,
            'nome' => $participante->name,
            'proprio' => $proprio,
        ]);

        // Quem saiu pode não conseguir mais ver a tarefa (ex.: Solicitante)
        if ($proprio) {
            return redirect('/dashboard')->with('msg', 'Você deixou de participar da tarefa.');
        }

        return redirect('/tasks/'.$task->id)->with('msg', $participante->name.' não participa mais da tarefa.');
    }
}
