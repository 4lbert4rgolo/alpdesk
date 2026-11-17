<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Papéis de uma pessoa em uma tarefa:
 * - quem abriu (user_id): edita, exclui, cancela e reabre a tarefa;
 * - responsáveis (users / tabela task_user): atendem a tarefa e mudam a situação;
 * - participantes (participantes / tabela task_participants): acompanham, comentam
 *   e registram autorizações, mas não editam nem atendem.
 * Quem tem "tarefas.gerenciar_todas" pode fazer tudo em qualquer tarefa.
 */
class Task extends Model
{
    use HasFactory;

    // Apenas estes campos podem ser gravados de uma vez (create/update)
    protected $fillable = [
        'title',
        'description',
        'deadline',
        'priority',
        'items',
        'image',
        'equipment_id',
        'user_id',
        'status',
        'concluida_em',
        'concluida_por',
        'solucao',
    ];

    protected $casts = [
        'items' => 'array',
        'deadline' => 'datetime',
        'priority' => 'integer',
        'concluida_em' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'aberta',
        'priority' => 2,
    ];

    // ---------------------------------------------------------------- Relações

    /** Quem abriu a tarefa */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Responsáveis (quem atende a tarefa) */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /** Participantes (acompanham, comentam e autorizam) */
    public function participantes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_participants')
            ->withPivot('added_by')
            ->withTimestamps();
    }

    public function atividades(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->orderBy('created_at')->orderBy('id');
    }

    /** Equipamento ligado. Mesmo se ele for para a lixeira, a tarefa continua mostrando qual era. */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    public function concluidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluida_por');
    }

    // ------------------------------------------------------- Situação e prioridade

    public static function statusAbertos(): array
    {
        return config('tarefas.status_abertos', ['aberta', 'em_andamento', 'aguardando']);
    }

    public function estaAberta(): bool
    {
        return in_array($this->status, self::statusAbertos(), true);
    }

    public function statusLabel(): string
    {
        return config('tarefas.status')[$this->status] ?? (string) $this->status;
    }

    public function prioridadeLabel(): string
    {
        return config('tarefas.prioridades')[(int) $this->priority][0] ?? 'Média';
    }

    public function ehUrgente(): bool
    {
        return (int) $this->priority >= 4;
    }

    /** Venceu o prazo e ainda está em aberto */
    public function estaVencida(): bool
    {
        return $this->estaAberta() && $this->deadline && $this->deadline->lt(today());
    }

    public function scopeEmAberto(Builder $consulta): Builder
    {
        return $consulta->whereIn('status', self::statusAbertos());
    }

    public function scopeEncerradas(Builder $consulta): Builder
    {
        return $consulta->whereNotIn('status', self::statusAbertos());
    }

    // ------------------------------------------------------------------- Papéis

    public function ehCriador(?User $usuario): bool
    {
        return $usuario !== null && (int) $this->user_id === (int) $usuario->id;
    }

    public function ehResponsavel(?User $usuario): bool
    {
        return $usuario !== null && $this->users->contains('id', $usuario->id);
    }

    public function ehParticipante(?User $usuario): bool
    {
        return $usuario !== null && $this->participantes->contains('id', $usuario->id);
    }

    private function gerenciaTodas(?User $usuario): bool
    {
        return $usuario !== null && $usuario->can('tarefas.gerenciar_todas');
    }

    // --------------------------------------------------------------- Permissões

    /**
     * Quem pode ver a tarefa (e a imagem dela): quem abriu, os responsáveis, os
     * participantes e quem tem permissão de assumir ou gerenciar tarefas.
     * Veja config/alpdesk.php (visibilidade_restrita).
     */
    public function visivelPara(?User $usuario): bool
    {
        if (! $usuario) {
            return false;
        }

        if (! config('alpdesk.visibilidade_restrita')) {
            return true;
        }

        if ($usuario->can('tarefas.assumir') || $usuario->can('tarefas.gerenciar_todas')) {
            return true;
        }

        return $this->ehCriador($usuario)
            || $this->ehResponsavel($usuario)
            || $this->ehParticipante($usuario);
    }

    /** Editar e excluir: quem abriu ou quem gerencia todas */
    public function podeGerenciar(?User $usuario): bool
    {
        return $this->ehCriador($usuario) || $this->gerenciaTodas($usuario);
    }

    /** Concluir, colocar em espera e retomar: responsáveis ou quem gerencia todas */
    public function podeAtender(?User $usuario): bool
    {
        return $this->ehResponsavel($usuario) || $this->gerenciaTodas($usuario);
    }

    /** Cancelar: quem abriu ou quem gerencia todas */
    public function podeCancelar(?User $usuario): bool
    {
        return $this->podeGerenciar($usuario);
    }

    /** Reabrir uma tarefa encerrada: quem abriu, responsáveis ou quem gerencia todas */
    public function podeReabrir(?User $usuario): bool
    {
        return $this->ehCriador($usuario) || $this->podeAtender($usuario);
    }

    /** Comentar: todos que podem ver a tarefa */
    public function podeComentar(?User $usuario): bool
    {
        return $this->visivelPara($usuario);
    }

    /** Registrar "Autorizo" / "Não autorizo": participantes ou quem gerencia todas */
    public function podeAutorizar(?User $usuario): bool
    {
        return $this->ehParticipante($usuario) || $this->gerenciaTodas($usuario);
    }

    /** Adicionar e remover participantes: quem abriu, responsáveis ou quem gerencia todas */
    public function podeGerenciarParticipantes(?User $usuario): bool
    {
        return $this->ehCriador($usuario) || $this->podeAtender($usuario);
    }

    /**
     * Versão em consulta de visivelPara(), para as listas.
     */
    public function scopeVisiveisPara(Builder $consulta, ?User $usuario): Builder
    {
        if (! $usuario) {
            return $consulta->whereRaw('1 = 0');
        }

        if (! config('alpdesk.visibilidade_restrita')
            || $usuario->can('tarefas.assumir')
            || $usuario->can('tarefas.gerenciar_todas')) {
            return $consulta;
        }

        return $consulta->where(function ($filtro) use ($usuario) {
            $filtro->where('user_id', $usuario->id)
                ->orWhereHas('users', fn ($responsaveis) => $responsaveis->where('users.id', $usuario->id))
                ->orWhereHas('participantes', fn ($participantes) => $participantes->where('users.id', $usuario->id));
        });
    }

    // --------------------------------------------------------------------- Outros

    /**
     * Pessoas envolvidas (quem abriu, responsáveis e participantes), sem repetir.
     */
    public function envolvidos()
    {
        return collect([$this->user])
            ->concat($this->users)
            ->concat($this->participantes)
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Grava um item na linha do tempo da tarefa.
     */
    public function registrar(string $tipo, ?User $usuario, ?string $corpo = null, array $dados = []): TaskActivity
    {
        return $this->atividades()->create([
            'user_id' => $usuario?->id,
            'tipo' => $tipo,
            'corpo' => $corpo,
            'dados' => $dados ?: null,
        ]);
    }

    /**
     * A imagem existe na pasta privada (storage/app/private/task-images)?
     */
    public function hasImage(): bool
    {
        return $this->image && Storage::disk('local')->exists('task-images/'.$this->image);
    }
}
