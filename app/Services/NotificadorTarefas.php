<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Notifications\AvisoTarefa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Decide quem recebe cada aviso por e-mail das tarefas.
 *
 * Regra geral: quem fez a ação nunca recebe o aviso dela. Se o envio falhar
 * (servidor de e-mail fora do ar, por exemplo), o erro vai para o log e a
 * tela continua funcionando normalmente.
 */
class NotificadorTarefas
{
    /**
     * Tarefa nova (ou encaminhada para outro setor): avisa quem atende o setor da tarefa.
     * Se o setor ainda não tem ninguém para atender, avisa quem gerencia todas as tarefas,
     * para a tarefa não ficar esquecida.
     */
    public function criada(Task $tarefa, User $autor, bool $encaminhada = false): void
    {
        $setor = $tarefa->sector?->name;

        $doSetor = $tarefa->sector_id
            ? User::with('accessGroups')->where('sector_id', $tarefa->sector_id)->get()
                ->filter(fn (User $u) => $u->can('tarefas.assumir'))
            : collect();

        $destino = $doSetor->filter(fn (User $u) => $u->ativo)->isNotEmpty()
            ? $doSetor
            : User::with('accessGroups')->get()->filter(fn (User $u) => $u->can('tarefas.gerenciar_todas'));

        $primeiraLinha = $encaminhada
            ? $autor->name.' encaminhou esta tarefa para o setor '.($setor ?? '—').'.'
            : $autor->name.' abriu uma tarefa'.($setor ? ' para o setor '.$setor : '').' com prioridade '.$tarefa->prioridadeLabel().'.';

        $this->enviar($destino, $autor, $tarefa, $encaminhada ? 'tarefa encaminhada para o seu setor' : 'nova tarefa aberta', [
            $primeiraLinha,
            'Prazo: '.$tarefa->deadline->format('d/m/Y').'.',
        ]);
    }

    /** Alguém assumiu: avisa quem abriu e os participantes */
    public function assumida(Task $tarefa, User $autor): void
    {
        $destino = collect([$tarefa->user])->concat($tarefa->participantes);

        $this->enviar($destino, $autor, $tarefa, 'tarefa assumida', [
            $autor->name.' assumiu a tarefa e vai cuidar dela.',
        ]);
    }

    /** Mudou a situação: avisa todos os envolvidos */
    public function statusAlterado(Task $tarefa, User $autor, ?string $observacao = null): void
    {
        $linhas = [$autor->name.' mudou a situação para "'.$tarefa->statusLabel().'".'];

        if ($observacao) {
            $linhas[] = Str::limit($observacao, 500);
        }

        $this->enviar($tarefa->envolvidos(), $autor, $tarefa, mb_strtolower($tarefa->statusLabel()), $linhas);
    }

    /** Comentário ou autorização: avisa todos os envolvidos */
    public function mensagem(Task $tarefa, TaskActivity $atividade, User $autor): void
    {
        $assunto = match ($atividade->tipo) {
            'autorizacao' => 'autorização registrada',
            'recusa' => 'autorização negada',
            default => 'novo comentário',
        };

        $this->enviar($tarefa->envolvidos(), $autor, $tarefa, $assunto, [
            $autor->name.' '.$atividade->descricao().':',
            '"'.Str::limit((string) $atividade->corpo, 500).'"',
        ]);
    }

    /** Virou participante: avisa a pessoa adicionada */
    /** Tarefa aberta por modelo que já define o responsável: avisa quem recebeu */
    public function atribuida(Task $tarefa, Collection $responsaveis, User $autor): void
    {
        $this->enviar($responsaveis, $autor, $tarefa, 'tarefa atribuída a você', [
            $autor->name.' abriu esta tarefa'.($tarefa->template ? ' pelo modelo "'.$tarefa->template->name.'"' : '').', e você é o responsável.',
            'Prazo: '.$tarefa->deadline->format('d/m/Y').'.',
        ]);
    }

    public function participanteAdicionado(Task $tarefa, User $participante, User $autor): void
    {
        $this->enviar(collect([$participante]), $autor, $tarefa, 'você foi adicionado como participante', [
            $autor->name.' adicionou você para acompanhar esta tarefa.',
            'Como participante, você pode ver a tarefa, comentar e registrar se autoriza ou não o que foi pedido.',
        ]);
    }

    /**
     * @param  Collection<int, User|null>  $destinatarios
     */
    private function enviar(Collection $destinatarios, User $autor, Task $tarefa, string $assunto, array $linhas): void
    {
        if (! config('tarefas.notificar_por_email')) {
            return;
        }

        $destino = $destinatarios
            ->filter()
            ->unique('id')
            ->reject(fn (User $u) => (int) $u->id === (int) $autor->id)
            ->filter(fn (User $u) => ! empty($u->email) && $u->ativo)
            ->values();

        if ($destino->isEmpty()) {
            return;
        }

        try {
            Notification::send($destino, new AvisoTarefa($tarefa, $assunto, $linhas));
        } catch (Throwable $erro) {
            report($erro);
        }
    }
}
