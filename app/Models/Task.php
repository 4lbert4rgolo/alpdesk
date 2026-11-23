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
 *
 * Setor (sector_id): para qual setor a tarefa foi aberta (TI, Financeiro...).
 * Quem tem "tarefas.assumir" vê e assume as tarefas do PRÓPRIO setor.
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
        'sector_id',
        'task_template_id',
        'respostas',
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
        'respostas' => 'array',
    ];

    protected $attributes = [
        'status' => 'aberta',
        'priority' => 2,
    ];

    /**
     * Tarefa criada sem setor (por exemplo, por um script) vai para o setor padrão.
     */
    protected static function booted(): void
    {
        static::creating(function (Task $tarefa) {
            if (! $tarefa->sector_id) {
                $tarefa->sector_id = Sector::padraoParaTarefas()?->id;
            }
        });
    }

    // ---------------------------------------------------------------- Relações

    /** Modelo usado para abrir a tarefa (ex.: Devolução de valores) */
    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'task_template_id');
    }

    /** Arquivos anexados (imagens e PDF) */
    public function anexos(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->orderBy('created_at')->orderBy('id');
    }

    /** Campos preenchidos a partir de um modelo (cópia dos campos com o valor) */
    public function temRespostas(): bool
    {
        return is_array($this->respostas) && count($this->respostas) > 0;
    }

    /** Setor para o qual a tarefa foi aberta */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

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

    /** A pessoa atende tarefas (pode assumir) e é do mesmo setor da tarefa */
    public function atendidaPeloSetorDe(?User $usuario): bool
    {
        return $usuario !== null
            && $usuario->sector_id
            && (int) $usuario->sector_id === (int) $this->sector_id
            && $usuario->can('tarefas.assumir');
    }

    // --------------------------------------------------------------- Permissões

    /**
     * Quem pode ver a tarefa (e a imagem dela): quem abriu, os responsáveis, os
     * participantes, quem atende o setor da tarefa e quem gerencia todas (administrador).
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

        if ($this->gerenciaTodas($usuario) || $this->atendidaPeloSetorDe($usuario)) {
            return true;
        }

        return $this->ehCriador($usuario)
            || $this->ehResponsavel($usuario)
            || $this->ehParticipante($usuario);
    }

    /** Assumir (virar responsável): quem atende o setor da tarefa ou quem gerencia todas */
    public function podeAssumir(?User $usuario): bool
    {
        return $this->gerenciaTodas($usuario) || $this->atendidaPeloSetorDe($usuario);
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

    /** Anexar arquivos: todos que podem ver a tarefa */
    public function podeAnexar(?User $usuario): bool
    {
        return $this->visivelPara($usuario);
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

        if (! config('alpdesk.visibilidade_restrita') || $usuario->can('tarefas.gerenciar_todas')) {
            return $consulta;
        }

        $atendeSetor = $usuario->sector_id && $usuario->can('tarefas.assumir');

        return $consulta->where(function ($filtro) use ($usuario, $atendeSetor) {
            $filtro->where('user_id', $usuario->id)
                ->when($atendeSetor, fn ($f) => $f->orWhere('sector_id', $usuario->sector_id))
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
