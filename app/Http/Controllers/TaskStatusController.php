<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\NotificadorTarefas;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Muda a situação da tarefa: concluir, aguardar, retomar, cancelar e reabrir.
 *
 * Quem pode o quê (veja os métodos pode* em App\Models\Task):
 * - concluir, aguardar, retomar: responsáveis ou quem gerencia todas;
 * - cancelar: quem abriu ou quem gerencia todas;
 * - reabrir: quem abriu, responsáveis ou quem gerencia todas.
 */
class TaskStatusController extends Controller
{
    public function __construct(private NotificadorTarefas $notificador) {}

    public function update(Request $request, $id)
    {
        $task = Task::with(['user', 'users', 'participantes'])->findOrFail($id);
        $usuario = $request->user();

        abort_unless($task->visivelPara($usuario), 404);

        $dados = $request->validate([
            'acao' => ['required', Rule::in(['concluir', 'aguardar', 'retomar', 'cancelar', 'reabrir'])],
            'observacao' => [
                Rule::requiredIf(fn () => in_array($request->input('acao'), ['concluir', 'cancelar', 'aguardar'], true)),
                'nullable', 'string', 'max:5000',
            ],
        ], [
            'observacao.required' => match ($request->input('acao')) {
                'concluir' => 'Conte o que foi feito para resolver a tarefa.',
                'cancelar' => 'Informe o motivo do cancelamento.',
                default => 'Informe o que a tarefa está aguardando.',
            },
        ]);

        $acao = $dados['acao'];
        $observacao = $dados['observacao'] ?? null;
        $aberta = $task->estaAberta();

        // Confere a permissão e se a ação faz sentido na situação atual
        [$permitido, $novoStatus] = match ($acao) {
            'concluir' => [$aberta && $task->podeAtender($usuario), 'concluida'],
            'aguardar' => [$aberta && $task->status !== 'aguardando' && $task->podeAtender($usuario), 'aguardando'],
            'retomar' => [$task->status === 'aguardando' && $task->podeAtender($usuario), 'em_andamento'],
            'cancelar' => [$aberta && $task->podeCancelar($usuario), 'cancelada'],
            'reabrir' => [! $aberta && $task->podeReabrir($usuario), null],
        };

        abort_unless($permitido, 403);

        // Ao reabrir, volta para "Em andamento" se ainda tiver responsável
        if ($acao === 'reabrir') {
            $novoStatus = $task->users->isNotEmpty() ? 'em_andamento' : 'aberta';
        }

        $anterior = $task->status;
        $encerrando = in_array($novoStatus, ['concluida', 'cancelada'], true);

        $task->update([
            'status' => $novoStatus,
            'concluida_em' => $encerrando ? now() : null,
            'concluida_por' => $encerrando ? $usuario->id : null,
            'solucao' => $acao === 'concluir' ? $observacao : ($encerrando ? $task->solucao : null),
        ]);

        $task->registrar('status', $usuario, $observacao, ['de' => $anterior, 'para' => $novoStatus]);
        $this->notificador->statusAlterado($task, $usuario, $observacao);

        $mensagens = [
            'concluir' => 'Tarefa concluída.',
            'aguardar' => 'Tarefa marcada como aguardando.',
            'retomar' => 'Tarefa retomada.',
            'cancelar' => 'Tarefa cancelada.',
            'reabrir' => 'Tarefa reaberta.',
        ];

        return redirect('/tasks/'.$task->id)->with('msg', $mensagens[$acao]);
    }
}
