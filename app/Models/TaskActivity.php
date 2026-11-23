<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um item da linha do tempo da tarefa.
 */
class TaskActivity extends Model
{
    // Tipos que são mensagens escritas por alguém
    public const MENSAGENS = ['comentario', 'autorizacao', 'recusa'];

    protected $fillable = [
        'task_id',
        'user_id',
        'tipo',
        'corpo',
        'dados',
    ];

    protected $casts = [
        'dados' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ehMensagem(): bool
    {
        return in_array($this->tipo, self::MENSAGENS, true);
    }

    /**
     * Frase curta do que aconteceu, por exemplo "assumiu a tarefa".
     */
    public function descricao(): string
    {
        $dados = $this->dados ?? [];
        $status = config('tarefas.status');

        return match ($this->tipo) {
            'criada' => 'abriu a tarefa',
            'editada' => 'editou a tarefa'.(! empty($dados['campos']) ? ' ('.implode(', ', $dados['campos']).')' : ''),
            'comentario' => 'comentou',
            'autorizacao' => 'autorizou',
            'recusa' => 'não autorizou',
            'assumiu' => 'assumiu a tarefa',
            'atribuida' => 'atribuiu a tarefa a '.($dados['nomes'] ?? '—').' (responsável definido no modelo)',
            'anexo' => (($dados['quantidade'] ?? 1) == 1 ? 'anexou 1 arquivo' : 'anexou '.$dados['quantidade'].' arquivos'),
            'anexo_removido' => 'removeu o anexo '.($dados['nome'] ?? ''),
            'encaminhada' => 'encaminhou a tarefa'.(! empty($dados['de']) ? ' de '.$dados['de'] : '').' para o setor '.($dados['para'] ?? '—'),
            'saiu' => 'deixou de ser responsável',
            'status' => 'mudou a situação de "'.($status[$dados['de'] ?? ''] ?? '?').'" para "'.($status[$dados['para'] ?? ''] ?? '?').'"',
            'participante_adicionado' => 'adicionou '.($dados['nome'] ?? 'alguém').' como participante',
            'participante_removido' => ($dados['proprio'] ?? false)
                ? 'deixou de participar'
                : 'removeu '.($dados['nome'] ?? 'alguém').' dos participantes',
            default => $this->tipo,
        };
    }

    public function icone(): string
    {
        return match ($this->tipo) {
            'criada' => 'add-circle-outline',
            'editada' => 'create-outline',
            'comentario' => 'chatbubble-ellipses-outline',
            'autorizacao' => 'checkmark-done-circle-outline',
            'recusa' => 'close-circle-outline',
            'assumiu' => 'hand-right-outline',
            'atribuida' => 'person-add-outline',
            'encaminhada' => 'arrow-redo-outline',
            'anexo', 'anexo_removido' => 'attach-outline',
            'saiu' => 'exit-outline',
            'status' => 'swap-horizontal-outline',
            'participante_adicionado', 'participante_removido' => 'people-outline',
            default => 'ellipse-outline',
        };
    }
}
