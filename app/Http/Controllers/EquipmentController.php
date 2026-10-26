<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Sector;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EquipmentController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->only(['loja', 'setor', 'tipo', 'status', 'pendencia', 'q']);

        $equipamentos = Equipment::with(['store', 'sector'])
            ->filtrar($filtros)
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('equipamentos.index', [
            'equipamentos' => $equipamentos,
            'filtros' => $filtros,
            'lojas' => Store::withCount('equipment')->orderBy('name')->get(),
            'semLoja' => Equipment::whereNull('store_id')->count(),
            'total' => Equipment::count(),
            'setores' => Sector::orderBy('name')->get(),
            'tipos' => config('equipamentos.tipos'),
            'situacoes' => config('equipamentos.status'),
        ]);
    }

    public function show(Equipment $equipamento)
    {
        return view('equipamentos.show', [
            'equipamento' => $equipamento->load(['store', 'sector'])->loadCount('tasks'),
            'tarefas' => $equipamento->tasks()->visiveisPara(auth()->user())->latest()->limit(20)->get(),
        ]);
    }

    public function create()
    {
        return view('equipamentos.form', ['equipamento' => null] + $this->opcoes());
    }

    public function store(Request $request)
    {
        $dados = $request->validate($this->regras(), $this->mensagens());

        $equipamento = Equipment::create($dados);

        return redirect()->route('equipamentos.show', $equipamento)->with('msg', 'Equipamento cadastrado.');
    }

    public function edit(Equipment $equipamento)
    {
        return view('equipamentos.form', ['equipamento' => $equipamento] + $this->opcoes());
    }

    public function update(Request $request, Equipment $equipamento)
    {
        $dados = $request->validate($this->regras($equipamento), $this->mensagens());

        $equipamento->update($dados);

        return redirect()->route('equipamentos.show', $equipamento)->with('msg', 'Equipamento atualizado.');
    }

    private function opcoes(): array
    {
        return [
            'lojas' => Store::orderBy('name')->get(),
            'setores' => Sector::orderBy('name')->get(),
            'tipos' => config('equipamentos.tipos'),
            'situacoes' => config('equipamentos.status'),
        ];
    }

    private function regras(?Equipment $equipamento = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(config('equipamentos.tipos'))],
            'status' => ['required', Rule::in(array_keys(config('equipamentos.status')))],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'asset_tag' => ['nullable', 'string', 'max:50', Rule::unique('equipment', 'asset_tag')->ignore($equipamento?->id)],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'hostname' => ['nullable', 'string', 'max:100'],
            'anydesk_id' => ['nullable', 'string', 'max:20'],
            'user_name' => ['nullable', 'string', 'max:255'],
            'specs' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function mensagens(): array
    {
        return [
            'asset_tag.unique' => 'Já existe outro equipamento com este patrimônio.',
        ];
    }
}
