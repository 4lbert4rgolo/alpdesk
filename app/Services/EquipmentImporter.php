<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Sector;
use App\Models\Store;
use App\Support\EquipmentColumns;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class EquipmentImporter
{
    public const MAX_LINHAS = 5000;

    /** chave da planilha => coluna do banco */
    private const CAMPOS = [
        'nome' => 'name',
        'tipo' => 'type',
        'situacao' => 'status',
        'patrimonio' => 'asset_tag',
        'numero_serie' => 'serial_number',
        'hostname' => 'hostname',
        'anydesk' => 'anydesk_id',
        'usuario' => 'user_name',
        'especificacoes' => 'specs',
        'observacoes' => 'notes',
    ];

    private const REGRAS = [
        'name' => ['required', 'string', 'max:255'],
        'asset_tag' => ['nullable', 'string', 'max:50'],
        'serial_number' => ['nullable', 'string', 'max:100'],
        'hostname' => ['nullable', 'string', 'max:100'],
        'anydesk_id' => ['nullable', 'string', 'max:20'],
        'user_name' => ['nullable', 'string', 'max:255'],
        'specs' => ['nullable', 'string', 'max:5000'],
        'notes' => ['nullable', 'string', 'max:2000'],
    ];

    /**
     * @param  array<int, array<int, mixed>>  $matriz  Linhas do arquivo (a primeira não vazia é o cabeçalho)
     * @param  array{simular?: bool, criar_lojas?: bool, atualizar?: bool}  $opcoes
     */
    public function importar(array $matriz, array $opcoes = []): array
    {
        $simular = (bool) ($opcoes['simular'] ?? false);
        $criarLojas = (bool) ($opcoes['criar_lojas'] ?? false);
        $atualizar = (bool) ($opcoes['atualizar'] ?? false);

        $r = [
            'simulado' => $simular,
            'criados' => 0,
            'atualizados' => 0,
            'ignorados' => 0,
            'lojas_novas' => 0,
            'setores_novos' => 0,
            'erros' => [],
            'avisos' => [],
            'falha' => null,
        ];

        $inicio = null;

        foreach ($matriz as $i => $linha) {
            if (! $this->linhaVazia($linha)) {
                $inicio = $i;
                break;
            }
        }

        if ($inicio === null) {
            $r['falha'] = 'O arquivo está vazio.';

            return $r;
        }

        $mapa = [];

        foreach ($matriz[$inicio] as $posicao => $titulo) {
            $chave = EquipmentColumns::chaveDoCabecalho((string) $titulo);

            if ($chave !== null && ! in_array($chave, $mapa, true)) {
                $mapa[$posicao] = $chave;
            }
        }

        if (! in_array('nome', $mapa, true)) {
            $r['falha'] = 'Não encontrei a coluna "Equipamento" na primeira linha do arquivo. Baixe o modelo para ver os títulos esperados.';

            return $r;
        }

        if (count($matriz) - $inicio - 1 > self::MAX_LINHAS) {
            $r['falha'] = 'O arquivo tem linhas demais. O limite é de '.self::MAX_LINHAS.' por importação.';

            return $r;
        }

        $lojas = $this->indexar(Store::all());
        $setores = $this->indexar(Sector::all());

        $tipos = [];
        foreach (config('equipamentos.tipos') as $tipo) {
            $tipos[EquipmentColumns::normalizar($tipo)] = $tipo;
        }

        $situacoes = [];
        foreach (config('equipamentos.status') as $chave => $rotulo) {
            $situacoes[EquipmentColumns::normalizar($chave)] = $chave;
            $situacoes[EquipmentColumns::normalizar($rotulo)] = $chave;
        }

        DB::beginTransaction();

        try {
            foreach ($matriz as $i => $linha) {
                if ($i <= $inicio || $this->linhaVazia($linha)) {
                    continue;
                }

                $this->processarLinha($linha, $mapa, $i + 1, $r, $lojas, $setores, $tipos, $situacoes, $atualizar, $criarLojas);
            }

            if ($simular) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (Throwable $e) {
            DB::rollBack();

            $r['criados'] = $r['atualizados'] = $r['ignorados'] = $r['lojas_novas'] = $r['setores_novos'] = 0;
            $r['falha'] = 'A importação foi cancelada e nada foi gravado: '.$e->getMessage();
        }

        return $r;
    }

    private function processarLinha(
        array $linha,
        array $mapa,
        int $numero,
        array &$r,
        array &$lojas,
        array &$setores,
        array $tipos,
        array $situacoes,
        bool $atualizar,
        bool $criarLojas
    ): void {
        $d = [];

        foreach ($mapa as $posicao => $chave) {
            $d[$chave] = $this->limpar($linha[$posicao] ?? null);
        }

        $patrimonio = array_key_exists('patrimonio', $d) ? EquipmentColumns::patrimonio($d['patrimonio']) : null;

        // 1) Localiza o equipamento: ID do sistema, depois ID da planilha antiga, depois patrimônio
        $existente = null;

        if (! empty($d['id'])) {
            $existente = ctype_digit($d['id']) ? Equipment::find((int) $d['id']) : null;

            if (! $existente) {
                $this->erro($r, $numero, "O ID {$d['id']} não existe no sistema. Deixe a coluna ID em branco para cadastrar um equipamento novo.");

                return;
            }
        }

        if (! $existente && ! empty($d['legacy_id']) && ctype_digit($d['legacy_id'])) {
            $existente = Equipment::where('legacy_id', (int) $d['legacy_id'])->first();
        }

        if (! $existente && $patrimonio !== null) {
            $existente = Equipment::where('asset_tag', $patrimonio)->first();
        }

        if ($existente && ! $atualizar) {
            $r['ignorados']++;

            return;
        }

        // 2) Campos que vieram na planilha
        $campos = [];

        foreach (self::CAMPOS as $chave => $coluna) {
            if (array_key_exists($chave, $d)) {
                $campos[$coluna] = $d[$chave];
            }
        }

        if (array_key_exists('patrimonio', $d)) {
            $campos['asset_tag'] = $patrimonio;
        }

        if (array_key_exists('anydesk', $d)) {
            $campos['anydesk_id'] = EquipmentColumns::anydesk($d['anydesk']);
        }

        if (array_key_exists('name', $campos) && $campos['name'] === null && $existente) {
            unset($campos['name']);
        }

        if (! $existente && empty($campos['name'])) {
            $this->erro($r, $numero, 'Falta o nome do equipamento (coluna "Equipamento").');

            return;
        }

        $this->ajustarLista($campos, 'type', $tipos, 'Tipo', 'Outros', $existente, $r, $numero);
        $this->ajustarLista($campos, 'status', $situacoes, 'Situação', 'em_uso', $existente, $r, $numero);

        // 3) Patrimônio não pode se repetir
        if (isset($campos['asset_tag'])) {
            $outro = Equipment::where('asset_tag', $campos['asset_tag'])
                ->when($existente, fn ($q) => $q->where('id', '!=', $existente->id))
                ->first();

            if ($outro) {
                $this->erro($r, $numero, "O patrimônio {$campos['asset_tag']} já pertence ao equipamento #{$outro->id} ({$outro->name}).");

                return;
            }
        }

        // 4) Tamanhos e formatos
        $validador = Validator::make($campos, array_intersect_key(self::REGRAS, $campos));

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $mensagem) {
                $this->erro($r, $numero, $mensagem);
            }

            return;
        }

        // 5) Loja e setor (por último, para não criar cadastros de linhas que falham)
        if (array_key_exists('loja', $d)) {
            [$campos['store_id'], $erro] = $this->resolverCadastro(Store::class, $d['loja'], $lojas, $criarLojas, $r, 'lojas_novas', 'Loja');

            if ($erro) {
                $this->erro($r, $numero, $erro);

                return;
            }
        }

        if (array_key_exists('setor', $d)) {
            [$campos['sector_id'], $erro] = $this->resolverCadastro(Sector::class, $d['setor'], $setores, $criarLojas, $r, 'setores_novos', 'Setor');

            if ($erro) {
                $this->erro($r, $numero, $erro);

                return;
            }
        }

        // 6) Grava
        if ($existente) {
            $existente->update($campos);
            $r['atualizados']++;

            return;
        }

        if (! empty($d['legacy_id']) && ctype_digit($d['legacy_id'])) {
            $campos['legacy_id'] = (int) $d['legacy_id'];
        }

        Equipment::create($campos);
        $r['criados']++;
    }

    /**
     * Confere tipo/situação com a lista aceita (sem diferenciar maiúsculas e acentos).
     * Valor desconhecido vira o padrão (cadastro novo) ou mantém o atual (atualização).
     */
    private function ajustarLista(array &$campos, string $coluna, array $aceitos, string $rotulo, string $padrao, ?Equipment $existente, array &$r, int $numero): void
    {
        if (array_key_exists($coluna, $campos)) {
            if ($campos[$coluna] === null) {
                unset($campos[$coluna]);
            } else {
                $achado = $aceitos[EquipmentColumns::normalizar($campos[$coluna])] ?? null;

                if ($achado === null) {
                    $this->aviso($r, $numero, "{$rotulo} \"{$campos[$coluna]}\" não existe; ".($existente ? 'mantive o valor atual.' : 'usei o padrão.'));
                    unset($campos[$coluna]);
                } else {
                    $campos[$coluna] = $achado;
                }
            }
        }

        if (! $existente && ! isset($campos[$coluna])) {
            $campos[$coluna] = $padrao;
        }
    }

    /** @return array{0: int|null, 1: string|null} [id, mensagem de erro] */
    private function resolverCadastro(string $classe, ?string $nome, array &$indice, bool $criar, array &$r, string $contador, string $rotulo): array
    {
        if ($nome === null) {
            return [null, null];
        }

        $chave = EquipmentColumns::normalizar($nome);

        if (isset($indice[$chave])) {
            return [$indice[$chave]->id, null];
        }

        if (! $criar) {
            return [null, "{$rotulo} \"{$nome}\" não existe. Corrija o nome ou marque a opção de criar lojas e setores novos."];
        }

        $novo = $classe::create(['name' => $nome]);
        $indice[$chave] = $novo;
        $r[$contador]++;

        return [$novo->id, null];
    }

    private function indexar($cadastros): array
    {
        $indice = [];

        foreach ($cadastros as $cadastro) {
            $indice[EquipmentColumns::normalizar($cadastro->name)] = $cadastro;
        }

        return $indice;
    }

    private function limpar(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (is_float($valor) && floor($valor) === $valor) {
            $valor = (string) (int) $valor;
        }

        $texto = trim((string) $valor);
        $texto = preg_replace("/^'([=+\-@])/", '$1', $texto);

        return $texto === '' ? null : $texto;
    }

    private function linhaVazia(array $linha): bool
    {
        foreach ($linha as $celula) {
            if (trim((string) ($celula ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function erro(array &$r, int $numero, string $mensagem): void
    {
        $r['erros'][] = ['linha' => $numero, 'mensagem' => $mensagem];
    }

    private function aviso(array &$r, int $numero, string $mensagem): void
    {
        $r['avisos'][] = ['linha' => $numero, 'mensagem' => $mensagem];
    }
}
