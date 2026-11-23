<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    protected $fillable = ['name', 'recebe_tarefas'];

    protected $casts = [
        'recebe_tarefas' => 'boolean',
    ];

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    /** Pessoas que trabalham neste setor */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Tarefas direcionadas a este setor */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** Setores que aparecem como destino ao abrir uma tarefa (ex.: TI, Financeiro) */
    public function scopeRecebemTarefas(Builder $consulta): Builder
    {
        return $consulta->where('recebe_tarefas', true);
    }

    /** Setor que já vem escolhido no formulário e recebe as tarefas criadas sem setor */
    public static function padraoParaTarefas(): ?self
    {
        return static::recebemTarefas()->where('name', config('tarefas.setor_padrao', 'TI'))->first()
            ?? static::recebemTarefas()->orderBy('name')->first();
    }

    /**
     * Setores cujas tarefas a pessoa enxerga como "fila" no menu e nos filtros:
     * quem gerencia todas as tarefas vê todos; quem atende vê o próprio setor.
     */
    public static function filasDe(?User $usuario)
    {
        if (! $usuario) {
            return collect();
        }

        if ($usuario->can('tarefas.gerenciar_todas')) {
            return static::recebemTarefas()->orderBy('name')->get();
        }

        if ($usuario->can('tarefas.assumir') && $usuario->sector_id) {
            return static::recebemTarefas()->where('id', $usuario->sector_id)->get();
        }

        return collect();
    }
}
