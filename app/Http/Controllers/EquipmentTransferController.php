<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Services\EquipmentImporter;
use App\Services\EquipmentSpreadsheet;
use App\Support\EquipmentColumns;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class EquipmentTransferController extends Controller
{
    /**
     * Exporta a lista com os filtros que estão na tela (loja, setor, tipo...).
     */
    public function exportar(Request $request)
    {
        $formato = $request->query('formato') === 'xls' ? 'xls' : 'csv';

        if ($formato === 'xls' && ! EquipmentSpreadsheet::xlsDisponivel()) {
            return back()->with('msg', EquipmentSpreadsheet::AVISO_XLS);
        }

        $filtros = $request->only(['loja', 'setor', 'tipo', 'status', 'pendencia', 'q']);

        $linhas = Equipment::with(['store', 'sector'])
            ->filtrar($filtros)
            ->orderBy('id')
            ->get()
            ->map(fn (Equipment $equipamento) => EquipmentColumns::linha($equipamento))
            ->all();

        return $this->baixar($formato, 'equipamentos-'.now()->format('Y-m-d'), $linhas);
    }

    /**
     * Arquivo modelo: só os títulos e uma linha de exemplo.
     */
    public function modelo(Request $request)
    {
        $formato = $request->query('formato') === 'xls' ? 'xls' : 'csv';

        if ($formato === 'xls' && ! EquipmentSpreadsheet::xlsDisponivel()) {
            return back()->with('msg', EquipmentSpreadsheet::AVISO_XLS);
        }

        return $this->baixar($formato, 'modelo-equipamentos', [EquipmentColumns::linhaDeExemplo()]);
    }

    public function form()
    {
        return view('equipamentos.importar', [
            'xlsDisponivel' => EquipmentSpreadsheet::xlsDisponivel(),
            'relatorio' => null,
        ]);
    }

    public function importar(Request $request, EquipmentImporter $importador)
    {
        $request->validate([
            'arquivo' => ['required', 'file', 'max:5120', 'extensions:csv,txt,xls,xlsx'],
            'acao' => ['nullable', Rule::in(['simular', 'importar'])],
        ], [
            'arquivo.required' => 'Escolha um arquivo para importar.',
            'arquivo.file' => 'Escolha um arquivo para importar.',
            'arquivo.max' => 'O arquivo pode ter no máximo 5 MB.',
            'arquivo.extensions' => 'Use um arquivo CSV, XLS ou XLSX.',
        ]);

        $arquivo = $request->file('arquivo');

        try {
            $matriz = EquipmentSpreadsheet::ler($arquivo->getRealPath(), $arquivo->getClientOriginalExtension());
        } catch (Throwable $e) {
            return back()->withErrors(['arquivo' => 'Não foi possível ler o arquivo: '.$e->getMessage()]);
        }

        $relatorio = $importador->importar($matriz, [
            'simular' => $request->input('acao') !== 'importar',
            'criar_lojas' => $request->boolean('criar_lojas'),
            'atualizar' => $request->boolean('atualizar'),
        ]);

        return view('equipamentos.importar', [
            'xlsDisponivel' => EquipmentSpreadsheet::xlsDisponivel(),
            'relatorio' => $relatorio,
        ]);
    }

    private function baixar(string $formato, string $nome, array $linhas)
    {
        $cabecalho = EquipmentColumns::cabecalho();

        return $formato === 'xls'
            ? EquipmentSpreadsheet::xls($cabecalho, $linhas, $nome.'.xls')
            : EquipmentSpreadsheet::csv($cabecalho, $linhas, $nome.'.csv');
    }
}
