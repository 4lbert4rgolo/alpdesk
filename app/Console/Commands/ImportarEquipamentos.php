<?php

namespace App\Console\Commands;

use App\Models\Equipment;
use App\Models\Sector;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportarEquipamentos extends Command
{
    protected $signature = 'alpdesk:importar-equipamentos
                            {arquivo? : Caminho do CSV (padrão: database/imports/equipamentos_inicial.csv)}
                            {--simular : Mostra o resultado sem gravar nada}
                            {--atualizar : Também atualiza os equipamentos que já foram importados}';

    protected $description = 'Importa o inventário inicial de equipamentos a partir de um CSV, separando por loja';

    private const COLUNAS = [
        'legacy_id', 'loja', 'setor', 'tipo', 'nome', 'usuario', 'hostname',
        'numero_serie', 'anydesk', 'patrimonio', 'especificacoes', 'observacoes',
    ];

    public function handle(): int
    {
        $caminho = $this->argument('arquivo') ?: database_path('imports/equipamentos_inicial.csv');

        if (! is_file($caminho)) {
            $this->error("Arquivo não encontrado: {$caminho}");

            return self::FAILURE;
        }

        $arquivo = fopen($caminho, 'r');
        $cabecalho = fgetcsv($arquivo, 0, ',', '"', '');

        if (! is_array($cabecalho)) {
            fclose($arquivo);
            $this->error('O arquivo está vazio.');

            return self::FAILURE;
        }

        $cabecalho[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cabecalho[0]);
        $cabecalho = array_map('trim', $cabecalho);

        $faltando = array_diff(self::COLUNAS, $cabecalho);

        if (! empty($faltando)) {
            fclose($arquivo);
            $this->error('Faltam colunas no arquivo: '.implode(', ', $faltando));

            return self::FAILURE;
        }

        $simular = (bool) $this->option('simular');
        $atualizar = (bool) $this->option('atualizar');
        $tiposValidos = config('equipamentos.tipos');

        $resumo = [
            'criados' => 0,
            'atualizados' => 0,
            'ignorados' => 0,
            'lojas_novas' => 0,
            'setores_novos' => 0,
            'patrimonios_em_conflito' => 0,
        ];
        $porLoja = [];
        $numeroDaLinha = 1;

        DB::beginTransaction();

        try {
            while (($linha = fgetcsv($arquivo, 0, ',', '"', '')) !== false) {
                $numeroDaLinha++;

                if (count($linha) === 1 && trim((string) $linha[0]) === '') {
                    continue; // linha em branco
                }

                if (count($linha) !== count($cabecalho)) {
                    $this->warn("Linha {$numeroDaLinha} ignorada: o número de colunas é diferente do cabeçalho.");
                    $resumo['ignorados']++;

                    continue;
                }

                $d = array_combine($cabecalho, $linha);

                $nomeLoja = $this->valor($d['loja']);
                $nomeSetor = $this->valor($d['setor']);
                $loja = null;
                $setor = null;

                if ($nomeLoja !== null) {
                    $loja = Store::firstOrCreate(['name' => $nomeLoja]);

                    if ($loja->wasRecentlyCreated) {
                        $resumo['lojas_novas']++;
                    }
                }

                if ($nomeSetor !== null) {
                    $setor = Sector::firstOrCreate(['name' => $nomeSetor]);

                    if ($setor->wasRecentlyCreated) {
                        $resumo['setores_novos']++;
                    }
                }

                $tipo = in_array($d['tipo'], $tiposValidos, true) ? $d['tipo'] : 'Outros';
                $patrimonio = $this->valor($d['patrimonio']);
                $observacoes = $this->valor($d['observacoes']);
                $legacyId = (int) $d['legacy_id'];
                $existente = Equipment::where('legacy_id', $legacyId)->first();

                // Patrimônio que já pertence a outro equipamento: não repete a etiqueta e deixa um aviso
                if ($patrimonio !== null) {
                    $outro = Equipment::where('asset_tag', $patrimonio)->first();

                    if ($outro && (! $existente || $outro->id !== $existente->id)) {
                        $aviso = "Patrimônio {$patrimonio} já pertence a outro equipamento no sistema. Confirmar o número correto.";
                        $observacoes = $observacoes === null ? $aviso : $observacoes.' '.$aviso;
                        $patrimonio = null;
                        $resumo['patrimonios_em_conflito']++;
                    }
                }

                $campos = [
                    'store_id' => $loja?->id,
                    'sector_id' => $setor?->id,
                    'type' => $tipo,
                    'name' => $d['nome'],
                    'asset_tag' => $patrimonio,
                    'serial_number' => $this->valor($d['numero_serie']),
                    'hostname' => $this->valor($d['hostname']),
                    'anydesk_id' => $this->valor($d['anydesk']),
                    'user_name' => $this->valor($d['usuario']),
                    'specs' => $this->valor($d['especificacoes']),
                    'notes' => $observacoes,
                ];

                if ($existente) {
                    if ($atualizar) {
                        $existente->update($campos);
                        $resumo['atualizados']++;
                    } else {
                        $resumo['ignorados']++;

                        continue;
                    }
                } else {
                    Equipment::create($campos + ['legacy_id' => $legacyId, 'status' => 'em_uso']);
                    $resumo['criados']++;
                }

                $chave = $nomeLoja ?? 'Sem loja definida';
                $porLoja[$chave] = ($porLoja[$chave] ?? 0) + 1;
            }

            if ($simular) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (Throwable $e) {
            DB::rollBack();
            fclose($arquivo);
            $this->error('Importação cancelada, nada foi gravado: '.$e->getMessage());

            return self::FAILURE;
        }

        fclose($arquivo);

        $this->newLine();
        $this->info($simular ? 'SIMULAÇÃO: nada foi gravado.' : 'Importação concluída.');

        $this->table(['Resultado', 'Quantidade'], [
            ['Equipamentos criados', $resumo['criados']],
            ['Equipamentos atualizados', $resumo['atualizados']],
            ['Linhas ignoradas (já existiam ou inválidas)', $resumo['ignorados']],
            ['Lojas novas', $resumo['lojas_novas']],
            ['Setores novos', $resumo['setores_novos']],
            ['Patrimônios em conflito (gravados sem etiqueta)', $resumo['patrimonios_em_conflito']],
        ]);

        arsort($porLoja);

        $this->table(
            ['Loja', 'Equipamentos'],
            collect($porLoja)->map(fn ($quantidade, $loja) => [$loja, $quantidade])->values()->all()
        );

        return self::SUCCESS;
    }

    private function valor(?string $texto): ?string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? null : $texto;
    }
}
