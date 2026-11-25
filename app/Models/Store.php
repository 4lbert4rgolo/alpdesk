<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Empresa (nas telas). A tabela continua "stores" porque antes se chamava "Loja".
 *
 * tipo "empresa": tem razão social e CNPJ, e é a controladora dos dados no termo LGPD.
 * tipo "local":   lugar sem CNPJ próprio (ex.: Escritório). Serve para equipamentos e
 *                 pessoas; no termo, vale a "empresa responsável" escolhida no cadastro.
 */
class Store extends Model
{
    public const TIPO_EMPRESA = 'empresa';

    public const TIPO_LOCAL = 'local';

    protected $fillable = [
        'name', 'tipo', 'empresa_responsavel_id',
        'razao_social', 'cnpj', 'endereco', 'encarregado_nome', 'encarregado_email',
    ];

    protected $attributes = [
        'tipo' => self::TIPO_EMPRESA,
    ];

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    /** Pessoas que trabalham nesta empresa ou local */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Para um local: a empresa que responde pelos dados de quem trabalha nele */
    public function empresaResponsavel(): BelongsTo
    {
        return $this->belongsTo(self::class, 'empresa_responsavel_id');
    }

    public function ehLocal(): bool
    {
        return $this->tipo === self::TIPO_LOCAL;
    }

    /** Só as empresas de verdade (com CNPJ), sem os locais */
    public function scopeEmpresas(Builder $consulta): Builder
    {
        return $consulta->where('tipo', self::TIPO_EMPRESA);
    }

    /**
     * Qual empresa aparece no termo LGPD de quem está aqui:
     * a própria empresa ou, se for um local, a empresa responsável (pode ser nenhuma).
     */
    public function empresaParaTermos(): ?self
    {
        return $this->ehLocal() ? $this->empresaResponsavel : $this;
    }

    /** Já tem tudo o que o termo LGPD pede? (locais não precisam: usam a empresa responsável) */
    public function dadosLgpdCompletos(): bool
    {
        return filled($this->razao_social) && filled($this->cnpj) && filled($this->endereco)
            && filled($this->encarregado_nome) && filled($this->encarregado_email);
    }
}
