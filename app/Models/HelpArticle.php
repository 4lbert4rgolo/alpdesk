<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Um conteúdo da Central de Ajuda: comunicado, pergunta frequente, tutorial, circular ou documento.
 *
 * Comunicado = recado do dia a dia, mostrado na tela Início (Home).
 * Circular   = aviso formal, que pode pedir "Li e estou ciente".
 *
 * Quem vê: setores e empresas marcados no conteúdo. Nenhum setor marcado = todos os setores;
 * nenhuma empresa marcada = todas as empresas. Quem tem a permissão "faq.gerenciar" vê tudo,
 * inclusive os rascunhos.
 */
class HelpArticle extends Model
{
    /** tipo => [singular, plural, ícone (ionicons)] */
    public const TIPOS = [
        'comunicado' => ['Comunicado', 'Comunicados', 'newspaper-outline'],
        'faq' => ['Pergunta frequente', 'Perguntas frequentes', 'help-circle-outline'],
        'tutorial' => ['Tutorial', 'Tutoriais', 'school-outline'],
        'circular' => ['Circular', 'Circulares', 'megaphone-outline'],
        'documento' => ['Documento', 'Documentos', 'folder-open-outline'],
    ];

    protected $fillable = [
        'tipo',
        'titulo',
        'resumo',
        'conteudo',
        'categoria',
        'exige_ciencia',
        'fixado',
        'publicado',
        'publicado_em',
    ];

    protected function casts(): array
    {
        return [
            'exige_ciencia' => 'boolean',
            'fixado' => 'boolean',
            'publicado' => 'boolean',
            'publicado_em' => 'datetime',
        ];
    }

    // ------------------------------------------------------------------ Relações

    public function setores(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'help_article_sector');
    }

    public function lojas(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'help_article_store');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(HelpAttachment::class)->orderBy('nome_original');
    }

    public function leituras(): HasMany
    {
        return $this->hasMany(HelpRead::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizado_por');
    }

    // ------------------------------------------------------------------ Quem vê

    /**
     * Filtra a consulta para mostrar só o que a pessoa pode ver.
     *
     * whereDoesntHave('setores') = conteúdo sem setor marcado (vale para todos).
     * orWhereHas('setores', ...)  = conteúdo marcado para o setor da pessoa.
     * O mesmo raciocínio vale para as empresas.
     */
    public function scopeVisivelPara(Builder $consulta, ?User $usuario): Builder
    {
        if (! $usuario) {
            return $consulta->whereRaw('1 = 0');
        }

        if ($usuario->can('faq.gerenciar')) {
            return $consulta;
        }

        return $consulta
            ->where('publicado', true)
            ->where(function (Builder $q) use ($usuario) {
                $q->whereDoesntHave('setores');

                if ($usuario->sector_id) {
                    $q->orWhereHas('setores', fn (Builder $s) => $s->whereKey($usuario->sector_id));
                }
            })
            ->where(function (Builder $q) use ($usuario) {
                $q->whereDoesntHave('lojas');

                if ($usuario->store_id) {
                    $q->orWhereHas('lojas', fn (Builder $l) => $l->whereKey($usuario->store_id));
                }
            });
    }

    /**
     * Esta pessoa pode ver ESTE conteúdo? (usa o mesmo filtro do scope acima)
     *
     * O nome é diferente do scope de propósito: se o model tivesse um método comum
     * chamado visivelPara(), HelpArticle::visivelPara() chamaria esse método em vez do scope.
     */
    public function estaVisivelPara(?User $usuario): bool
    {
        return static::query()->whereKey($this->id)->visivelPara($usuario)->exists();
    }

    /** Circulares que pedem ciência e que a pessoa ainda não confirmou */
    public function scopePendentesDeCiencia(Builder $consulta, User $usuario): Builder
    {
        return $consulta
            ->where('tipo', 'circular')
            ->where('exige_ciencia', true)
            ->where('publicado', true)
            ->whereDoesntHave('leituras', fn (Builder $l) => $l->where('user_id', $usuario->id));
    }

    /**
     * Pessoas que devem ler: contas ativas do público do conteúdo (setores e empresas).
     * Usado no relatório "quem leu / quem falta".
     */
    public function publicoAlvo(): Builder
    {
        $setores = $this->setores->modelKeys();
        $lojas = $this->lojas->modelKeys();

        return User::query()
            ->where('ativo', true)
            ->when($setores, fn (Builder $q) => $q->whereIn('sector_id', $setores))
            ->when($lojas, fn (Builder $q) => $q->whereIn('store_id', $lojas));
    }

    // ------------------------------------------------------------------ Apresentação

    public function tipoNome(): string
    {
        return self::TIPOS[$this->tipo][0] ?? $this->tipo;
    }

    public function icone(): string
    {
        return self::TIPOS[$this->tipo][2] ?? 'document-outline';
    }

    /**
     * Texto do conteúdo em HTML. Aceita Markdown simples (**negrito**, listas, links).
     * html_input "escape": qualquer HTML digitado aparece como texto, sem virar código.
     * Isso impede que alguém coloque um <script> dentro de uma circular.
     */
    public function conteudoHtml(): string
    {
        return Str::markdown((string) $this->conteudo, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    /** "Todos os setores" ou "TI, Financeiro" */
    public function publicoDescricao(): string
    {
        $setores = $this->setores->pluck('name')->sort()->implode(', ') ?: 'Todos os setores';
        $lojas = $this->lojas->pluck('name')->sort()->implode(', ') ?: 'todas as empresas';

        return $setores.' · '.$lojas;
    }

    public function lidoPor(?User $usuario): ?HelpRead
    {
        return $usuario ? $this->leituras()->where('user_id', $usuario->id)->first() : null;
    }
}
