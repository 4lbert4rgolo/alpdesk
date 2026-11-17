<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lê e grava os arquivos de equipamentos.
 * CSV funciona sem nada extra. XLS e XLSX precisam do pacote
 * phpoffice/phpspreadsheet (composer require phpoffice/phpspreadsheet).
 */
class EquipmentSpreadsheet
{
    public const AVISO_XLS = 'Para usar XLS ou XLSX, instale o pacote: composer require phpoffice/phpspreadsheet';

    public static function xlsDisponivel(): bool
    {
        return class_exists(Spreadsheet::class);
    }

    /**
     * Lê o arquivo e devolve todas as linhas (a primeira é o cabeçalho).
     *
     * @return array<int, array<int, mixed>>
     */
    public static function ler(string $caminho, string $extensao): array
    {
        $extensao = strtolower($extensao);

        if (in_array($extensao, ['xls', 'xlsx'], true)) {
            if (! self::xlsDisponivel()) {
                throw new RuntimeException(self::AVISO_XLS);
            }

            $leitor = IOFactory::createReader($extensao === 'xls' ? 'Xls' : 'Xlsx');
            $leitor->setReadDataOnly(true);
            $planilha = $leitor->load($caminho);

            return $planilha->getActiveSheet()->toArray(null, true, true, false);
        }

        return self::lerCsv($caminho);
    }

    private static function lerCsv(string $caminho): array
    {
        $conteudo = (string) file_get_contents($caminho);
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);

        // O Excel em português costuma salvar CSV em Windows-1252
        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        }

        $primeiraLinha = strtok($conteudo, "\n") ?: '';
        $separador = substr_count($primeiraLinha, ';') >= substr_count($primeiraLinha, ',') ? ';' : ',';

        $fluxo = fopen('php://temp', 'r+');
        fwrite($fluxo, $conteudo);
        rewind($fluxo);

        $linhas = [];

        while (($linha = fgetcsv($fluxo, 0, $separador, '"', '')) !== false) {
            $linhas[] = $linha;
        }

        fclose($fluxo);

        return $linhas;
    }

    /**
     * CSV com separador ";" e acentos corretos no Excel (UTF-8 com BOM).
     */
    public static function csv(array $cabecalho, array $linhas, string $nomeArquivo): StreamedResponse
    {
        return response()->streamDownload(function () use ($cabecalho, $linhas) {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF");

            fputcsv($saida, $cabecalho, ';', '"', '');

            foreach ($linhas as $linha) {
                fputcsv($saida, array_map([self::class, 'protegerCelula'], $linha), ';', '"', '');
            }

            fclose($saida);
        }, $nomeArquivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Arquivo XLS (Excel 97-2003). Todas as células vão como texto, para o
     * Excel não tirar o zero à esquerda do patrimônio.
     */
    public static function xls(array $cabecalho, array $linhas, string $nomeArquivo): StreamedResponse
    {
        if (! self::xlsDisponivel()) {
            throw new RuntimeException(self::AVISO_XLS);
        }

        return response()->streamDownload(function () use ($cabecalho, $linhas) {
            $planilha = new Spreadsheet();
            $aba = $planilha->getActiveSheet();
            $aba->setTitle('Equipamentos');

            foreach (array_merge([$cabecalho], $linhas) as $i => $linha) {
                foreach (array_values($linha) as $j => $valor) {
                    $celula = Coordinate::stringFromColumnIndex($j + 1).($i + 1);
                    $texto = self::protegerCelula($valor === null ? '' : (string) $valor);
                    $aba->setCellValueExplicit($celula, $texto, DataType::TYPE_STRING);
                }
            }

            $ultima = Coordinate::stringFromColumnIndex(count($cabecalho));
            $aba->getStyle("A1:{$ultima}1")->getFont()->setBold(true);
            $aba->freezePane('A2');

            foreach ($cabecalho as $indice => $titulo) {
                $coluna = $aba->getColumnDimension(Coordinate::stringFromColumnIndex($indice + 1));

                if (in_array($titulo, ['Especificações', 'Observações'], true)) {
                    $coluna->setWidth(60);
                } else {
                    $coluna->setAutoSize(true);
                }
            }

            (new Xls($planilha))->save('php://output');
        }, $nomeArquivo, ['Content-Type' => 'application/vnd.ms-excel']);
    }

    /**
     * Texto que começa com = + - @ pode virar fórmula no Excel. Um apóstrofo na
     * frente evita isso (a importação remove o apóstrofo).
     */
    public static function protegerCelula(mixed $valor): mixed
    {
        if (is_string($valor) && $valor !== '' && preg_match('/^[=+\-@\t\r]/', $valor)) {
            return "'".$valor;
        }

        return $valor;
    }
}
