<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * Conversa do chat interno: individual (duas pessoas) ou grupo (com nome e gerentes).
 */
class Conversation extends Model
{
    protected $fillable = ['tipo', 'nome', 'created_by', 'ultima_mensagem_em'];

    protected $casts = [
        'ultima_mensagem_em' => 'datetime',
    ];

    public function participantes(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['ultima_lida_id', 'gerente'])->withTimestamps();
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function ultimaMensagem(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function ehGrupo(): bool
    {
        return $this->tipo === 'grupo';
    }

    public function temParticipante(?User $usuario): bool
    {
        return $usuario !== null && $this->participantes()->where('users.id', $usuario->id)->exists();
    }

    /** Gerenciar o grupo (nome e membros): gerentes do grupo ou quem tem "chat.grupos" */
    public function podeGerenciar(?User $usuario): bool
    {
        if (! $usuario || ! $this->ehGrupo()) {
            return false;
        }

        return $usuario->can('chat.grupos')
            || $this->participantes()->where('users.id', $usuario->id)->wherePivot('gerente', true)->exists();
    }

    /** Nome mostrado para a pessoa: o do grupo, ou o da outra pessoa na conversa individual */
    public function nomePara(?User $usuario): string
    {
        if ($this->ehGrupo()) {
            return $this->nome ?: 'Grupo';
        }

        $outra = $this->participantes->firstWhere('id', '!=', $usuario?->id);

        return $outra?->name ?? 'Conversa';
    }

    /** Marca como lidas todas as mensagens que existem agora */
    public function marcarComoLida(User $usuario): void
    {
        $ultima = Message::where('conversation_id', $this->id)->max('id');

        if ($ultima) {
            $this->participantes()->updateExistingPivot($usuario->id, ['ultima_lida_id' => $ultima]);
        }
    }

    /** Conversa individual entre duas pessoas: a que já existe ou uma nova */
    public static function individualEntre(User $uma, User $outra): self
    {
        $existente = static::where('tipo', 'individual')
            ->whereHas('participantes', fn ($q) => $q->where('users.id', $uma->id))
            ->whereHas('participantes', fn ($q) => $q->where('users.id', $outra->id))
            ->first();

        if ($existente) {
            return $existente;
        }

        $conversa = static::create(['tipo' => 'individual', 'created_by' => $uma->id]);
        $conversa->participantes()->attach([$uma->id, $outra->id]);

        return $conversa;
    }

    /** Total de mensagens não lidas da pessoa, somando todas as conversas (para o menu) */
    public static function naoLidasDe(?User $usuario): int
    {
        if (! $usuario) {
            return 0;
        }

        return DB::table('messages')
            ->join('conversation_user', 'conversation_user.conversation_id', '=', 'messages.conversation_id')
            ->where('conversation_user.user_id', $usuario->id)
            ->where(fn ($q) => $q->whereNull('messages.user_id')->orWhere('messages.user_id', '!=', $usuario->id))
            ->whereRaw('messages.id > COALESCE(conversation_user.ultima_lida_id, 0)')
            ->count();
    }
}
