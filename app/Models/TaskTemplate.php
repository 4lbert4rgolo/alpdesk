<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

/**
 * Modelo de tarefa: um formulário pronto (ex.: "Devolução de valores") com campos
 * que a pessoa preenche ao abrir a tarefa. Cada campo é guardado em "campos" como:
 *   ['chave' => 'valor', 'rotulo' => 'Valor da devolução', 'tipo' => 'valor', 'obrigatorio' => true, 'opcoes' => []]
 */
class TaskTemplate extends Model
{
    /** Tipos de campo: chave => texto mostrado na tela de modelos */
    public const TIPOS = [
        'texto' => 'Texto curto',
        'texto_longo' => 'Texto longo',
        'data' => 'Data',
        'valor' => 'Valor em R$',
        'numero' => 'Número',
        'opcoes' => 'Lista de opções',
        'loja' => 'Empresa (lista das empresas cadastradas)',
        'setor' => 'Setor (lista dos setores cadastrados)',
    ];

    protected $fillable = ['name', 'descricao', 'sector_id', 'prioridade', 'responsaveis', 'participantes', 'instrucoes', 'campos', 'pede_equipamento', 'ativo'];

    protected $casts = [
        'campos' => 'array',
        'responsaveis' => 'array',
        'participantes' => 'array',
        'pede_equipamento' => 'boolean',
        'prioridade' => 'integer',
        'ativo' => 'boolean',
    ];

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Responsáveis automáticos que ainda valem: contas ativas que podem atender
     * e são do setor do modelo (se a pessoa mudou de setor, deixa de receber).
     */
    public function responsaveisValidos()
    {
        if (empty($this->responsaveis)) {
            return collect();
        }

        return User::with('accessGroups')
            ->whereIn('id', $this->responsaveis)
            ->where('ativo', true)
            ->when($this->sector_id, fn ($q) => $q->where('sector_id', $this->sector_id))
            ->orderBy('name')
            ->get()
            ->filter(fn (User $u) => $u->can('tarefas.assumir'))
            ->values();
    }

    public function scopeAtivos(Builder $consulta): Builder
    {
        return $consulta->where('ativo', true);
    }

    /**
     * Regras de validação dos campos (respostas.<chave>), montadas a partir da lista de campos.
     * Recebe a lista porque, na edição, a tarefa usa a cópia dos campos que guardou.
     */
    public static function regras(array $campos): array
    {
        $regras = ['respostas' => ['nullable', 'array']];

        foreach ($campos as $campo) {
            $base = [! empty($campo['obrigatorio']) ? 'required' : 'nullable'];

            $regras['respostas.'.$campo['chave']] = array_merge($base, match ($campo['tipo']) {
                'texto' => ['string', 'max:255'],
                'texto_longo' => ['string', 'max:5000'],
                'data' => ['date'],
                // Aceita "1.234,56", "1234,56" e "1234.56"; a conversão é feita em valorEmNumero()
                'valor' => ['string', 'max:20', 'regex:/^\d{1,3}(\.\d{3})*(,\d{1,2})?$|^\d+([.,]\d{1,2})?$/'],
                'numero' => ['integer'],
                'opcoes' => [Rule::in($campo['opcoes'] ?? [])],
                'loja', 'setor' => [Rule::in(self::listaDoTipo($campo['tipo']))],
                default => ['string', 'max:255'],
            });
        }

        return $regras;
    }

    /**
     * Opções dos campos "Empresa" e "Setor": os nomes cadastrados em Setores e empresas.
     * Guardamos o nome (e não o número) para a tarefa continuar legível mesmo se o cadastro mudar.
     */
    public static function listaDoTipo(string $tipo): array
    {
        return match ($tipo) {
            'loja' => Store::orderBy('name')->pluck('name')->all(),
            'setor' => Sector::orderBy('name')->pluck('name')->all(),
            default => [],
        };
    }

    /** Nomes amigáveis nas mensagens de erro ("O campo Valor da devolução é obrigatório") */
    public static function nomesDosCampos(array $campos): array
    {
        return collect($campos)->mapWithKeys(fn ($c) => ['respostas.'.$c['chave'] => $c['rotulo']])->all();
    }

    /**
     * Respostas como ficam gravadas na tarefa: uma cópia de cada campo com o valor.
     * Assim a tarefa continua certa mesmo se o modelo for alterado ou excluído depois.
     */
    public static function montarRespostas(array $campos, array $valores): array
    {
        return collect($campos)->map(function ($campo) use ($valores) {
            $valor = $valores[$campo['chave']] ?? null;

            if ($campo['tipo'] === 'valor' && $valor !== null && $valor !== '') {
                $valor = self::valorEmNumero($valor);
            }

            return [
                'chave' => $campo['chave'],
                'rotulo' => $campo['rotulo'],
                'tipo' => $campo['tipo'],
                'obrigatorio' => (bool) ($campo['obrigatorio'] ?? false),
                'opcoes' => $campo['opcoes'] ?? [],
                'valor' => $valor === '' ? null : $valor,
            ];
        })->values()->all();
    }

    /** "1.234,56" => "1234.56" (texto, para não perder centavos com arredondamento) */
    public static function valorEmNumero(string $valor): string
    {
        $valor = trim($valor);

        if (str_contains($valor, ',')) {
            // Jeito brasileiro: ponto separa milhar e vírgula separa centavos
            $valor = str_replace(['.', ','], ['', '.'], $valor);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $valor)) {
            // "1.250" sem vírgula é mil duzentos e cinquenta, não 1,25
            $valor = str_replace('.', '', $valor);
        }

        return number_format((float) $valor, 2, '.', '');
    }

    /** Valor para mostrar na tarefa, conforme o tipo do campo */
    public static function formatar(array $resposta): string
    {
        $valor = $resposta['valor'] ?? null;

        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($resposta['tipo'] ?? 'texto') {
            'data' => \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y'),
            'valor' => 'R$ '.number_format((float) $valor, 2, ',', '.'),
            default => (string) $valor,
        };
    }
}
