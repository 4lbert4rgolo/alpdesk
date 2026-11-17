<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentMovement;
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
            'naLixeira' => $request->user()->can('equipamentos.excluir') ? Equipment::onlyTrashed()->count() : 0,
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
            'movimentacoes' => $equipamento->movimentacoes()->with('user')->limit(50)->get(),
        ]);
    }

    /**
     * Etiqueta para imprimir e colar no equipamento, com QR Code que abre a ficha.
     */
    public function etiqueta(Equipment $equipamento)
    {
        return view('equipamentos.etiqueta', ['equipamento' => $equipamento->load(['store', 'sector'])]);
    }

    public function create()
    {
        return view('equipamentos.form', ['equipamento' => null] + $this->opcoes());
    }

    public function store(Request $request)
    {
        $dados = $request->validate($this->regras(), $this->mensagens());
        unset($dados['motivo_alteracao']);

        $equipamento = Equipment::create($dados);

        $this->registrar($equipamento, $request, 'cadastro', 'Cadastrado');

        return redirect()->route('equipamentos.show', $equipamento)->with('msg', 'Equipamento cadastrado.');
    }

    public function edit(Equipment $equipamento)
    {
        return view('equipamentos.form', ['equipamento' => $equipamento] + $this->opcoes());
    }

    public function update(Request $request, Equipment $equipamento)
    {
        $dados = $request->validate($this->regras($equipamento), $this->mensagens());
        $motivo = trim((string) ($dados['motivo_alteracao'] ?? '')) ?: null;
        unset($dados['motivo_alteracao']);

        $equipamento->fill($dados);
        $alteracoes = $this->alteracoes($equipamento);
        $equipamento->save();

        // Histórico: o que mudou, com o valor antes e depois (e o motivo, se informado)
        if ($alteracoes) {
            $this->registrar(
                $equipamento,
                $request,
                'alteracao',
                'Alterou '.implode(', ', array_map('mb_strtolower', array_keys($alteracoes))),
                $alteracoes,
                $motivo,
            );
        }

        return redirect()->route('equipamentos.show', $equipamento)
            ->with('msg', $alteracoes ? 'Equipamento atualizado.' : 'Nada foi alterado.');
    }

    // ------------------------------------------------------- Exclusão (lixeira)

    /**
     * Manda o equipamento para a lixeira. O motivo fica no histórico.
     */
    public function destroy(Request $request, Equipment $equipamento)
    {
        $dados = $request->validate(
            ['motivo' => ['required', 'string', 'max:1000']],
            ['motivo.required' => 'Informe o motivo da exclusão.']
        );

        $this->registrar($equipamento, $request, 'exclusao', 'Enviado para a lixeira', null, trim($dados['motivo']));
        $equipamento->delete();

        return redirect()->route('equipamentos.index')
            ->with('msg', "Equipamento \"{$equipamento->name}\" enviado para a lixeira.");
    }

    public function lixeira()
    {
        $equipamentos = Equipment::onlyTrashed()
            ->with(['store', 'sector', 'movimentacoes' => fn ($m) => $m->where('tipo', 'exclusao')->with('user')])
            ->orderByDesc('deleted_at')
            ->paginate(25);

        return view('equipamentos.lixeira', ['equipamentos' => $equipamentos]);
    }

    public function restaurar(Request $request, $id)
    {
        $equipamento = Equipment::onlyTrashed()->findOrFail($id);

        $equipamento->restore();
        $this->registrar($equipamento, $request, 'restauracao', 'Restaurado da lixeira');

        return redirect()->route('equipamentos.show', $equipamento)->with('msg', 'Equipamento restaurado.');
    }

    // ------------------------------------------------------------- Comentários

    public function comentar(Request $request, Equipment $equipamento)
    {
        $dados = $request->validate(
            ['comentario' => ['required', 'string', 'max:2000']],
            ['comentario.required' => 'Escreva o comentário antes de enviar.']
        );

        $this->registrar($equipamento, $request, 'comentario', 'Comentário', null, trim($dados['comentario']));

        return redirect()->to(route('equipamentos.show', $equipamento).'#historico')->with('msg', 'Comentário adicionado.');
    }

    public function apagarComentario(Request $request, EquipmentMovement $comentario)
    {
        abort_unless($comentario->podeApagar($request->user()), 403);

        $equipamento = $comentario->equipment;
        $comentario->delete();

        if ($equipamento->trashed()) {
            return redirect()->route('equipamentos.lixeira')->with('msg', 'Comentário apagado.');
        }

        return redirect()->to(route('equipamentos.show', $equipamento).'#historico')->with('msg', 'Comentário apagado.');
    }

    // ------------------------------------------------------------------ Apoio

    private function registrar(
        Equipment $equipamento,
        Request $request,
        string $tipo,
        string $descricao,
        ?array $alteracoes = null,
        ?string $comentario = null,
    ): EquipmentMovement {
        return EquipmentMovement::create([
            'equipment_id' => $equipamento->id,
            'user_id' => $request->user()?->id,
            'tipo' => $tipo,
            'descricao' => $descricao,
            'alteracoes' => $alteracoes,
            'comentario' => $comentario,
        ]);
    }

    /**
     * Campos alterados, com o valor antes e depois em texto.
     * Especificações e observações (textos longos) aparecem só como alteradas.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function alteracoes(Equipment $equipamento): array
    {
        $situacoes = config('equipamentos.status');
        $vazio = fn ($valor) => $valor === null || $valor === '';
        $texto = fn ($valor) => $vazio($valor) ? '—' : (string) $valor;

        $campos = [
            'name' => ['Nome', $texto],
            'type' => ['Tipo', $texto],
            'asset_tag' => ['Patrimônio', $texto],
            'serial_number' => ['Nº de série', $texto],
            'store_id' => ['Loja', fn ($id) => $id ? (Store::find($id)?->name ?? '—') : 'Sem loja'],
            'sector_id' => ['Setor', fn ($id) => $id ? (Sector::find($id)?->name ?? '—') : 'Sem setor'],
            'user_name' => ['Usuário', $texto],
            'status' => ['Situação', fn ($valor) => $situacoes[$valor] ?? $texto($valor)],
            'hostname' => ['Nome na rede', $texto],
            'anydesk_id' => ['AnyDesk', $texto],
            'specs' => ['Especificações', null],
            'notes' => ['Observações', null],
        ];

        $alteracoes = [];

        foreach ($campos as $campo => $info) {
            [$rotulo, $formatar] = $info;

            if (! $equipamento->isDirty($campo)) {
                continue;
            }

            $antes = $equipamento->getOriginal($campo);
            $depois = $equipamento->{$campo};

            $alteracoes[$rotulo] = $formatar === null
                ? [$vazio($antes) ? '—' : 'texto anterior', $vazio($depois) ? '—' : 'texto novo']
                : [$formatar($antes), $formatar($depois)];
        }

        return $alteracoes;
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
            // A regra unique também enxerga a lixeira (o banco não deixa repetir o patrimônio)
            'asset_tag' => ['nullable', 'string', 'max:50', Rule::unique('equipment', 'asset_tag')->ignore($equipamento?->id)],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'hostname' => ['nullable', 'string', 'max:100'],
            'anydesk_id' => ['nullable', 'string', 'max:20'],
            'user_name' => ['nullable', 'string', 'max:255'],
            'specs' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'motivo_alteracao' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function mensagens(): array
    {
        return [
            'asset_tag.unique' => 'Já existe outro equipamento com este patrimônio (confira também a lixeira).',
        ];
    }
}
