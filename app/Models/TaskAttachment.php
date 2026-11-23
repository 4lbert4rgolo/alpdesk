<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Arquivo anexado a uma tarefa (imagem ou PDF), guardado em storage/app/private/task-attachments.
 */
class TaskAttachment extends Model
{
    public const PASTA = 'task-attachments';

    protected $fillable = ['task_id', 'user_id', 'nome_original', 'arquivo', 'mime', 'tamanho'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function caminho(): string
    {
        return self::PASTA.'/'.$this->arquivo;
    }

    public function ehImagem(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function existe(): bool
    {
        return Storage::disk('local')->exists($this->caminho());
    }

    /** Tamanho para mostrar na tela, ex.: "820 KB" ou "2,4 MB" */
    public function tamanhoLegivel(): string
    {
        if ($this->tamanho >= 1024 * 1024) {
            return number_format($this->tamanho / 1024 / 1024, 1, ',', '.').' MB';
        }

        return max(1, (int) round($this->tamanho / 1024)).' KB';
    }

    /** Apagar: quem anexou ou quem pode editar a tarefa (quem abriu ou administrador) */
    public function podeApagar(?User $usuario, Task $tarefa): bool
    {
        return $usuario !== null
            && ((int) $this->user_id === (int) $usuario->id || $tarefa->podeGerenciar($usuario));
    }
}
