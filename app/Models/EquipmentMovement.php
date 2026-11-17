<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um registro no histórico do equipamento.
 *
 * tipo: cadastro, alteracao, comentario, exclusao ou restauracao.
 */
class EquipmentMovement extends Model
{
    protected $fillable = [
        'equipment_id',
        'user_id',
        'tipo',
        'descricao',
        'alteracoes',
        'comentario',
    ];

    protected $casts = [
        'alteracoes' => 'array',
    ];

    protected $attributes = [
        'tipo' => 'alteracao',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ehComentario(): bool
    {
        return $this->tipo === 'comentario';
    }

    /** Quem escreveu o comentário ou quem pode excluir equipamentos pode apagar um comentário */
    public function podeApagar(?User $usuario): bool
    {
        return $this->ehComentario() && $usuario
            && ((int) $this->user_id === (int) $usuario->id || $usuario->can('equipamentos.excluir'));
    }

    public function icone(): string
    {
        return match ($this->tipo) {
            'cadastro' => 'add-circle-outline',
            'comentario' => 'chatbubble-ellipses-outline',
            'exclusao' => 'trash-outline',
            'restauracao' => 'refresh-outline',
            default => 'swap-horizontal-outline',
        };
    }
}
