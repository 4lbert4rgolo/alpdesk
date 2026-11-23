<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Modelos de tarefa: formulários prontos (ex.: "Devolução de valores") com os campos
 * que a pessoa preenche ao abrir a tarefa. O anexo continua disponível em todas as tarefas.
 */
class TaskTemplateController extends Controller
{
    public function index()
    {
        return view('admin.modelos.index', [
            'modelos' => TaskTemplate::with('sector')->withCount('tasks')->orderByDesc('ativo')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.modelos.form', ['modelo' => null] + $this->opcoes());
    }

    public function store(Request $request)
    {
        $modelo = TaskTemplate::create($this->dados($request));

        return redirect()->route('admin.modelos.edit', $modelo)->with('msg', "Modelo \"{$modelo->name}\" criado.");
    }

    public function edit(TaskTemplate $modelo)
    {
        return view('admin.modelos.form', ['modelo' => $modelo->loadCount('tasks')] + $this->opcoes($modelo));
    }

    public function update(Request $request, TaskTemplate $modelo)
    {
        $modelo->update($this->dados($request, $modelo));

        return redirect()->route('admin.modelos.edit', $modelo)
            ->with('msg', 'Modelo atualizado. As tarefas já abertas continuam com os campos de quando foram criadas.');
    }

    /**
     * Excluir não apaga as tarefas: elas guardaram uma cópia dos campos e do que foi preenchido.
     */
    public function destroy(TaskTemplate $modelo)
    {
        $modelo->delete();

        return redirect()->route('admin.modelos.index')->with('msg', "Modelo \"{$modelo->name}\" excluído. As tarefas abertas com ele continuam como estão.");
    }

    // ------------------------------------------------------------------ Apoio

    private function opcoes(?TaskTemplate $modelo = null): array
    {
        return [
            'setores' => Sector::where(fn ($q) => $q->where('recebe_tarefas', true)->when($modelo?->sector_id, fn ($w) => $w->orWhere('id', $modelo->sector_id)))
                ->orderBy('name')
                ->get(['id', 'name']),
            'tipos' => TaskTemplate::TIPOS,
            // Quem pode ser responsável automático: contas ativas que atendem tarefas (o formulário filtra pelo setor)
            'atendentes' => User::ativos()->with(['accessGroups', 'sector:id,name'])->orderBy('name')->get()
                ->filter(fn (User $u) => $u->can('tarefas.assumir'))->values(),
            'pessoas' => User::ativos()->with('sector:id,name')->orderBy('name')->get(['id', 'name', 'sector_id']),
        ];
    }

    /**
     * Valida o formulário e monta a lista de campos.
     * Cada campo ganha uma "chave" (ex.: valor_da_devolucao) que não muda quando o rótulo é editado.
     */
    private function dados(Request $request, ?TaskTemplate $modelo = null): array
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'sector_id' => ['nullable', 'integer', Rule::exists('sectors', 'id')->where(
                fn ($q) => $q->where('recebe_tarefas', true)->when($modelo?->sector_id, fn ($w) => $w->orWhere('id', $modelo->sector_id))
            )],
            'prioridade' => ['nullable', 'integer', Rule::in(array_keys(config('tarefas.prioridades')))],
            'instrucoes' => ['nullable', 'string', 'max:3000'],
            'campos' => ['required', 'array', 'min:1', 'max:30'],
            'campos.*.chave' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'campos.*.rotulo' => ['required', 'string', 'max:120'],
            'campos.*.tipo' => ['required', Rule::in(array_keys(TaskTemplate::TIPOS))],
            'campos.*.opcoes' => ['nullable', 'string', 'max:1000'],
            'campos.*.obrigatorio' => ['nullable', 'boolean'],
            'responsaveis' => ['nullable', 'array', 'max:10'],
            'responsaveis.*' => ['integer', Rule::exists('users', 'id')->where('ativo', true)],
            'participantes' => ['nullable', 'array', 'max:20'],
            'participantes.*' => ['integer', Rule::exists('users', 'id')->where('ativo', true)],
        ], [
            'responsaveis.*.exists' => 'Um dos responsáveis escolhidos não está mais ativo.',
            'participantes.*.exists' => 'Um dos participantes escolhidos não está mais ativo.',
            'campos.required' => 'Adicione pelo menos um campo.',
            'campos.*.rotulo.required' => 'Todo campo precisa de um nome.',
            'sector_id.exists' => 'Escolha um setor que recebe tarefas.',
        ]);

        $campos = [];
        $usadas = [];

        // $indice é a posição que veio do formulário (pode pular números quando uma linha é removida)
        foreach ($dados['campos'] as $indice => $campo) {
            $opcoes = $campo['tipo'] === 'opcoes'
                ? collect(preg_split('/\r\n|\n|;/', (string) ($campo['opcoes'] ?? '')))->map(fn ($o) => trim($o))->filter()->unique()->values()->all()
                : [];

            if ($campo['tipo'] === 'opcoes' && count($opcoes) < 2) {
                throw ValidationException::withMessages([
                    "campos.{$indice}.opcoes" => "O campo \"{$campo['rotulo']}\" é uma lista: escreva pelo menos duas opções, uma por linha.",
                ]);
            }

            // Chave: a que já existia (edição) ou uma nova a partir do rótulo, sem repetir
            $chave = ($campo['chave'] ?? '') ?: (Str::slug(Str::limit($campo['rotulo'], 40, ''), '_') ?: 'campo');
            $base = $chave;
            $n = 2;

            while (in_array($chave, $usadas, true)) {
                $chave = $base.'_'.$n++;
            }

            $usadas[] = $chave;

            $campos[] = [
                'chave' => $chave,
                'rotulo' => trim($campo['rotulo']),
                'tipo' => $campo['tipo'],
                // Checkbox desmarcado não é enviado pelo navegador
                'obrigatorio' => ! empty($campo['obrigatorio']),
                'opcoes' => $opcoes,
            ];
        }

        // Responsáveis automáticos: precisam poder atender tarefas e ser do setor do modelo
        $responsaveis = collect($dados['responsaveis'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        if ($responsaveis->isNotEmpty()) {
            $validos = User::with('accessGroups')->whereIn('id', $responsaveis)->get()
                ->filter(fn (User $u) => $u->can('tarefas.assumir') && (empty($dados['sector_id']) || (int) $u->sector_id === (int) $dados['sector_id']));

            if ($validos->count() !== $responsaveis->count()) {
                throw ValidationException::withMessages([
                    'responsaveis' => 'Os responsáveis precisam ser do setor do modelo e poder atender tarefas (permissão "Assumir tarefas").',
                ]);
            }
        }

        // Participantes padrão: sem repetir quem já é responsável
        $participantes = collect($dados['participantes'] ?? [])->map(fn ($id) => (int) $id)->unique()->diff($responsaveis)->values();

        return [
            'name' => $dados['name'],
            'responsaveis' => $responsaveis->all() ?: null,
            'participantes' => $participantes->all() ?: null,
            'descricao' => $dados['descricao'] ?? null,
            'sector_id' => $dados['sector_id'] ?? null,
            'prioridade' => $dados['prioridade'] ?? null,
            'instrucoes' => $dados['instrucoes'] ?? null,
            'campos' => $campos,
            'pede_equipamento' => $request->boolean('pede_equipamento'),
            'ativo' => $request->boolean('ativo'),
        ];
    }
}
