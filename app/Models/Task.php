<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Task extends Model
{
    use HasFactory;

    protected $casts = [
        'items' => 'array',
        'deadline' => 'datetime',
    ];

    protected $guarded = [];


    public function user() {
        return $this->belongsTo('App\Models\User');
    }

    public function users() {
        return $this->belongsToMany('App\Models\User');
    }

    public function equipment() {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * A imagem existe na pasta privada (storage/app/private/task-images)?
     */
    public function hasImage(): bool
    {
        return $this->image && Storage::disk('local')->exists('task-images/'.$this->image);
    }

    /**
     * Quem pode ver esta tarefa (e a imagem dela): quem criou, os responsáveis e
     * quem tem permissão de assumir ou gerenciar tarefas. Veja config/alpdesk.php.
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

        return (int) $this->user_id === (int) $usuario->id
            || $this->users->contains('id', $usuario->id);
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
                ->orWhereHas('users', fn ($responsaveis) => $responsaveis->where('users.id', $usuario->id));
        });
    }
    
}
