<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $fillable = [
        'store_id',
        'sector_id',
        'type',
        'name',
        'asset_tag',
        'serial_number',
        'hostname',
        'anydesk_id',
        'user_name',
        'status',
        'specs',
        'notes',
        'legacy_id',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function statusLabel(): string
    {
        return config('equipamentos.status')[$this->status] ?? (string) $this->status;
    }

    /**
     * Filtros da listagem: loja, setor, tipo, situação, pendência e busca livre.
     * Nos filtros de loja e setor, o valor "sem" traz quem ainda não tem.
     */
    public function scopeFiltrar(Builder $query, array $f): Builder
    {
        return $query
            ->when(($f['loja'] ?? '') === 'sem', fn ($q) => $q->whereNull('store_id'))
            ->when(! empty($f['loja']) && $f['loja'] !== 'sem', fn ($q) => $q->where('store_id', (int) $f['loja']))
            ->when(($f['setor'] ?? '') === 'sem', fn ($q) => $q->whereNull('sector_id'))
            ->when(! empty($f['setor']) && $f['setor'] !== 'sem', fn ($q) => $q->where('sector_id', (int) $f['setor']))
            ->when(! empty($f['tipo']), fn ($q) => $q->where('type', $f['tipo']))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when(($f['pendencia'] ?? '') === 'sem_patrimonio', fn ($q) => $q->whereNull('asset_tag'))
            ->when(($f['pendencia'] ?? '') === 'com_observacao', fn ($q) => $q->whereNotNull('notes')->where('notes', '!=', ''))
            ->when(! empty($f['q']), function ($q) use ($f) {
                $termo = '%'.trim($f['q']).'%';

                $q->where(function ($w) use ($termo) {
                    $w->where('name', 'like', $termo)
                        ->orWhere('user_name', 'like', $termo)
                        ->orWhere('asset_tag', 'like', $termo)
                        ->orWhere('hostname', 'like', $termo)
                        ->orWhere('serial_number', 'like', $termo)
                        ->orWhere('anydesk_id', 'like', $termo);
                });
            });
    }
}
