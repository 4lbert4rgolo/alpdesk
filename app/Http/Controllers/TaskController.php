<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Sector;
use App\Models\Store;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\AnexosTarefa;
use App\Services\NotificadorTarefas;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(private NotificadorTarefas $notificador, private AnexosTarefa $anexos) {}

    /**
     * Lista de tarefas com busca e filtros: período de abertura, situação,
     * responsável, empresa e prioridade. Tudo vai na URL, então dá para salvar
     * ou compartilhar uma consulta.
     */
    public function index(Request $request)
    {
        $filtros = $this->filtrosDaLista($request);

        $query = Task::with(['users', 'equipment.store', 'sector', 'template'])
            ->visiveisPara(auth()->user());

        // Setor (fila): TI, Financeiro...
        if ($filtros['setor']) {
            $query->where('sector_id', (int) $filtros['setor']);
        }

        // Situação: abertas (padrão), encerradas, todas ou uma situação específica
        if ($filtros['situacao'] === 'abertas') {
            $query->emAberto();
        } elseif ($filtros['situacao'] === 'encerradas') {
            $query->encerradas();
        } elseif ($filtros['situacao'] !== 'todas') {
            $query->where('status', $filtros['situacao']);
        }

        // Período de abertura
        if ($filtros['de']) {
            $query->whereDate('created_at', '>=', $filtros['de']);
        }

        if ($filtros['ate']) {
            $query->whereDate('created_at', '<=', $filtros['ate']);
        }

        // Responsável: uma pessoa ou "sem responsável"
        if ($filtros['responsavel'] === 'sem') {
            $query->doesntHave('users');
        } elseif ($filtros['responsavel']) {
            $query->whereHas('users', fn ($r) => $r->where('users.id', (int) $filtros['responsavel']));
        }

        // Empresa (vem do equipamento ligado à tarefa) ou "sem empresa"
        if ($filtros['loja'] === 'sem') {
            $query->where(function ($semLoja) {
                $semLoja->whereNull('equipment_id')
                    ->orWhereHas('equipment', fn ($e) => $e->whereNull('store_id'));
            });
        } elseif ($filtros['loja']) {
            $query->whereHas('equipment', fn ($e) => $e->where('store_id', (int) $filtros['loja']));
        }

        if ($filtros['prioridade']) {
            $query->where('priority', $filtros['prioridade']);
        }

        // Modelo de tarefa (ex.: Devolução de valores) ou "tarefa livre"
        if ($filtros['modelo'] === 'livre') {
            $query->whereNull('task_template_id');
        } elseif ($filtros['modelo']) {
            $query->where('task_template_id', (int) $filtros['modelo']);
        }

        // Busca livre: número da tarefa (#12), título, descrição ou equipamento
        if ($filtros['search'] !== '') {
            $termo = '%'.$filtros['search'].'%';
            $numero = preg_match('/^#?(\d+)$/', $filtros['search'], $m) ? (int) $m[1] : null;

            $query->where(function ($busca) use ($termo, $numero) {
                if ($numero) {
                    $busca->orWhere('id', $numero);
                }

                $busca->orWhere('title', 'like', $termo)
                    ->orWhere('description', 'like', $termo)
                    ->orWhereHas('equipment', function ($equipamento) use ($termo) {
                        $equipamento->where('name', 'like', $termo)
                            ->orWhere('asset_tag', 'like', $termo)
                            ->orWhere('hostname', 'like', $termo);
                    });
            });
        }

        $tasks = $query
            ->orderByDesc('priority')
            ->orderBy('deadline')
            ->paginate(20)
            ->withQueryString();

        return view('welcome', [
            'tasks' => $tasks,
            'filtros' => $filtros,
            'search' => $filtros['search'],
            'situacao' => $filtros['situacao'],
            'responsaveis' => $this->opcoesDeResponsavel(),
            'lojas' => Store::orderBy('name')->get(['id', 'name']),
            // Setores que a pessoa pode escolher no filtro (administrador: todos; quem atende: o seu)
            'setores' => Sector::filasDe(auth()->user()),
            'setorAtual' => $filtros['setor'] ? Sector::find((int) $filtros['setor']) : null,
            'modelos' => TaskTemplate::orderBy('name')->get(['id', 'name', 'ativo']),
        ]);
    }

    /**
     * Lê e limpa os filtros da URL (valores inválidos são ignorados).
     */
    private function filtrosDaLista(Request $request): array
    {
        $data = function ($valor): ?string {
            if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
                return null;
            }

            try {
                return Carbon::createFromFormat('Y-m-d', $valor)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        };

        $de = $data($request->query('de'));
        $ate = $data($request->query('ate'));

        // Se a pessoa inverter as datas, troca uma pela outra
        if ($de && $ate && $de > $ate) {
            [$de, $ate] = [$ate, $de];
        }

        $situacao = (string) $request->query('situacao', 'abertas');

        if (! in_array($situacao, ['abertas', 'encerradas', 'todas'], true)
            && ! array_key_exists($situacao, config('tarefas.status'))) {
            $situacao = 'abertas';
        }

        $responsavel = (string) $request->query('responsavel', '');
        $loja = (string) $request->query('loja', '');
        $prioridade = (int) $request->query('prioridade');
        $setor = (string) $request->query('setor', '');
        $modelo = (string) $request->query('modelo', '');

        return [
            'search' => trim((string) $request->query('search', '')),
            'situacao' => $situacao,
            'de' => $de,
            'ate' => $ate,
            'responsavel' => ($responsavel === 'sem' || ctype_digit($responsavel)) ? $responsavel : '',
            'loja' => ($loja === 'sem' || ctype_digit($loja)) ? $loja : '',
            'setor' => ctype_digit($setor) ? $setor : '',
            'modelo' => ($modelo === 'livre' || ctype_digit($modelo)) ? $modelo : '',
            'prioridade' => array_key_exists($prioridade, config('tarefas.prioridades')) ? $prioridade : 0,
        ];
    }

    /**
     * Pessoas para o filtro de responsável: quem pode assumir tarefas e quem já foi responsável por alguma.
     */
    private function opcoesDeResponsavel()
    {
        $jaForamResponsaveis = DB::table('task_user')->distinct()->pluck('user_id')->map(fn ($id) => (int) $id);

        return User::with('accessGroups')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(fn (User $u) => $jaForamResponsaveis->contains($u->id) || $u->can('tarefas.assumir'))
            ->values();
    }

    /**
     * Nova tarefa. Se houver modelos ativos, primeiro a pessoa escolhe entre
     * "Tarefa livre" e um modelo (ex.: Devolução de valores).
     */
    public function create(Request $request)
    {
        $modelos = TaskTemplate::ativos()->with('sector')->orderBy('name')->get();

        // Voltando de um erro de validação, reabre o mesmo formulário (com o que foi preenchido)
        $idModelo = $request->query('modelo') ?: $request->old('task_template_id');
        $voltouComErro = $request->session()->hasOldInput();

        $modelo = $idModelo ? $modelos->firstWhere('id', (int) $idModelo) : null;

        // Link para um modelo que não existe (ou foi desativado)
        abort_if($request->filled('modelo') && ! $modelo, 404);

        if (! $modelo && $modelos->isNotEmpty() && ! $request->boolean('livre') && ! $voltouComErro) {
            return view('tasks.escolher', ['modelos' => $modelos]);
        }

        return view('tasks.create', [
            'modelo' => $modelo,
            'camposModelo' => $modelo?->campos ?? [],
            'equipamentos' => $this->equipamentosDeInformatica(),
            'setoresDestino' => $this->setoresDestino(),
            'setorPadrao' => $modelo?->sector_id ?? Sector::padraoParaTarefas()?->id,
            // Responsável já definido no modelo (aparece no formulário)
            'responsaveisModelo' => $modelo?->responsaveisValidos() ?? collect(),
            // Quem pode ser convidado como participante: contas ativas, menos quem está abrindo
            'pessoas' => User::ativos()->with('sector:id,name')->where('id', '!=', auth()->id())->orderBy('name')->get(['id', 'name', 'sector_id']),
        ]);
    }

    public function store(Request $request)
    {
        // Modelo escolhido (só os ativos podem ser usados para abrir tarefas novas)
        $modelo = $request->filled('task_template_id')
            ? TaskTemplate::ativos()->find((int) $request->input('task_template_id'))
            : null;

        if ($request->filled('task_template_id') && ! $modelo) {
            return back()->withInput()->withErrors(['title' => 'Este modelo de tarefa não está mais disponível.']);
        }

        $campos = $modelo?->campos ?? [];

        $dados = $request->validate(
            $this->regrasTarefa(nova: true, descricaoObrigatoria: ! $modelo)
                + $this->regrasEquipamento()
                + $this->regrasSetor()
                + AnexosTarefa::regras()
                + TaskTemplate::regras($campos)
                + [
                    // Participantes: contas ativas que vão acompanhar a tarefa
                    'participantes' => ['nullable', 'array', 'max:20'],
                    'participantes.*' => ['integer', Rule::exists('users', 'id')->where('ativo', true)],
                ],
            $this->mensagensTarefa() + AnexosTarefa::mensagens() + [
                'participantes.max' => 'Escolha no máximo 20 participantes.',
                'participantes.*.exists' => 'Uma das pessoas escolhidas não está mais ativa. Escolha de novo.',
            ],
            TaskTemplate::nomesDosCampos($campos)
        );
        $user = auth()->user();

        $task = new Task;
        $task->title = $dados['title'];
        $task->description = $dados['description'] ?? '';
        $task->deadline = $dados['deadline'];
        $task->priority = (int) $dados['priority'];
        $task->status = 'aberta';
        $task->user_id = $user->id;

        if ($modelo) {
            // O modelo define o setor; o "contexto do problema" é das tarefas de TI e não aparece aqui
            $task->task_template_id = $modelo->id;
            $task->respostas = TaskTemplate::montarRespostas($campos, $dados['respostas'] ?? []);
            $task->sector_id = $modelo->sector_id ?? $dados['sector_id'] ?? Sector::padraoParaTarefas()?->id;
            $task->items = [];
            $task->equipment_id = $modelo->pede_equipamento ? ($dados['equipment_id'] ?? null) : null;
        } else {
            $task->sector_id = $dados['sector_id'] ?? Sector::padraoParaTarefas()?->id;
            $task->items = $dados['items'] ?? [];
            $task->equipment_id = $dados['equipment_id'] ?? null;
        }

        $task->save();
        $task->registrar('criada', $user);

        // Anexos: imagens e PDF, guardados em pasta privada (fora de public)
        $this->anexos->salvar($task, $request->file('anexos', []), $user);

        // Responsável automático definido no modelo (pessoas do setor que podem atender)
        $responsaveis = $modelo
            ? $modelo->responsaveisValidos()->filter(fn (User $pessoa) => $task->podeAssumir($pessoa))->values()
            : collect();

        if ($responsaveis->isNotEmpty()) {
            $task->users()->attach($responsaveis->pluck('id'));
            $task->registrar('atribuida', $user, null, ['nomes' => $responsaveis->pluck('name')->join(', ', ' e ')]);
        }

        // "Eu mesmo vou atender": só para quem atende o setor escolhido (ou o administrador)
        $assumiu = false;

        if ($request->boolean('assumir_agora') && $task->podeAssumir($user) && ! $responsaveis->contains('id', $user->id)) {
            $task->users()->attach($user->id);
            $task->update(['status' => 'em_andamento']);
            $task->registrar('assumiu', $user);
            $assumiu = true;
        }

        // Participantes escolhidos no formulário (quem abriu e os responsáveis não entram na lista)
        $jaEnvolvidos = $responsaveis->pluck('id')->push($user->id)->map(fn ($id) => (int) $id);
        $convidados = User::whereIn('id', collect($dados['participantes'] ?? [])->map(fn ($id) => (int) $id)->unique()->diff($jaEnvolvidos))->get();

        foreach ($convidados as $participante) {
            $task->participantes()->attach($participante->id, ['added_by' => $user->id]);
            $task->registrar('participante_adicionado', $user, null, ['user_id' => $participante->id, 'nome' => $participante->name]);
            $this->notificador->participanteAdicionado($task, $participante, $user);
        }

        // Avisos: o responsável definido no modelo recebe o aviso direto; sem responsável, avisa o setor
        if ($responsaveis->isNotEmpty()) {
            $this->notificador->atribuida($task, $responsaveis, $user);
        } elseif (! $assumiu) {
            $this->notificador->criada($task, $user);
        }

        return redirect('/tasks/'.$task->id)->with('msg', "Tarefa #{$task->id} criada com sucesso!");
    }

    public function show($id)
    {
        $task = Task::with(['user', 'users', 'participantes', 'equipment.store', 'sector', 'template', 'anexos.user', 'atividades.user', 'concluidaPor'])
            ->findOrFail($id);

        // Quem não pode ver a tarefa recebe "não encontrada" (não revela que ela existe)
        abort_unless($task->visivelPara(auth()->user()), 404);

        // Pessoas que podem ser adicionadas como participantes (quem ainda não está na tarefa)
        $candidatos = collect();

        if ($task->podeGerenciarParticipantes(auth()->user())) {
            $candidatos = User::ativos()
                ->orderBy('name')
                ->whereNotIn('id', $task->envolvidos()->pluck('id'))
                ->get(['id', 'name', 'email']);
        }

        return view('tasks.show', ['task' => $task, 'candidatos' => $candidatos]);
    }

    /**
     * Minhas tarefas: as que abri, as que sou responsável e as que participo.
     */
    public function dashboard()
    {
        $user = auth()->user();
        $relacoes = ['users', 'equipment'];

        $criadas = $user->tasks()->with($relacoes)->get();
        $responsavel = $user->tasksAsParticipant()->with($relacoes)->get();
        $participo = $user->participacoes()->with($relacoes)->get();

        $tarefas = $criadas->concat($responsavel)->concat($participo)
            ->unique('id')
            ->sortBy([['priority', 'desc'], ['deadline', 'asc']])
            ->values();

        return view('tasks.dashboard', [
            'tarefas' => $tarefas,
            'idsCriadas' => $criadas->pluck('id')->all(),
            'idsResponsavel' => $responsavel->pluck('id')->all(),
            'idsParticipo' => $participo->pluck('id')->all(),
        ]);
    }

    /**
     * Quem é responsável pode deixar de ser.
     */
    public function sair($id)
    {
        $task = Task::with('users')->findOrFail($id);
        $user = auth()->user();

        abort_unless($task->ehResponsavel($user), 403);

        $task->users()->detach($user->id);
        $task->registrar('saiu', $user);

        // Sem nenhum responsável, a tarefa volta para "Aberta"
        if ($task->status === 'em_andamento' && $task->users()->count() === 0) {
            $task->update(['status' => 'aberta']);
        }

        return redirect('/dashboard')->with('msg', 'Você deixou de ser responsável pela tarefa.');
    }

    public function destroy($id)
    {
        $task = Task::findOrFail($id);

        // Quem criou a tarefa (ou tem a permissão de gerenciar todas) pode excluí-la
        abort_unless($task->podeGerenciar(auth()->user()), 403);

        if ($task->image) {
            Storage::disk('local')->delete('task-images/'.$task->image);
        }

        foreach ($task->anexos as $anexo) {
            Storage::disk('local')->delete($anexo->caminho());
        }

        $task->users()->detach();
        $task->delete(); // participantes e linha do tempo saem juntos (cascade)

        return redirect('/dashboard')->with('msg', 'Tarefa excluída com sucesso!');
    }

    public function edit($id)
    {
        $task = Task::with('template')->findOrFail($id);

        if (! $task->podeGerenciar(auth()->user())) {
            return redirect('/dashboard');
        }

        return view('tasks.edit', [
            'task' => $task,
            'modelo' => $task->template,
            // Na edição valem os campos copiados para a tarefa (o modelo pode ter mudado depois)
            'camposModelo' => $task->temRespostas() ? $task->respostas : [],
            'equipamentos' => $this->equipamentosDeInformatica($task->equipment_id),
            'setoresDestino' => $this->setoresDestino($task->sector_id),
            'setorPadrao' => $task->sector_id,
        ]);
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        // Quem criou a tarefa (ou tem a permissão de gerenciar todas) pode editá-la
        abort_unless($task->podeGerenciar(auth()->user()), 403);

        $campos = $task->temRespostas() ? $task->respostas : [];

        // Apenas os campos do formulário (evita alterar user_id e outros campos)
        $dados = $request->validate(
            $this->regrasTarefa(descricaoObrigatoria: ! $campos)
                + $this->regrasEquipamento($task)
                + $this->regrasSetor($task)
                + AnexosTarefa::regras($task)
                + TaskTemplate::regras($campos),
            $this->mensagensTarefa() + AnexosTarefa::mensagens(),
            TaskTemplate::nomesDosCampos($campos)
        );

        $data = [
            'title' => $dados['title'],
            'description' => $dados['description'] ?? '',
            'deadline' => $dados['deadline'],
            'priority' => (int) $dados['priority'],
            'sector_id' => $dados['sector_id'] ?? $task->sector_id,
        ];

        if ($campos) {
            $data['respostas'] = TaskTemplate::montarRespostas($campos, $dados['respostas'] ?? []);

            if ($task->template?->pede_equipamento) {
                $data['equipment_id'] = $dados['equipment_id'] ?? null;
            }
        } else {
            $data['items'] = $dados['items'] ?? [];
            $data['equipment_id'] = $dados['equipment_id'] ?? null;
        }

        $task->fill($data);
        $setorAnterior = $task->isDirty('sector_id') ? Sector::find($task->getOriginal('sector_id')) : null;
        $encaminhada = $task->isDirty('sector_id');
        $alterados = $this->camposAlterados($task);
        $task->save();

        $novosAnexos = $this->anexos->salvar($task, $request->file('anexos', []), auth()->user());

        if ($novosAnexos > 0) {
            $alterados[] = $novosAnexos === 1 ? '1 anexo novo' : "{$novosAnexos} anexos novos";
        }

        if ($alterados) {
            $task->registrar('editada', auth()->user(), null, ['campos' => $alterados]);
        }

        // Mudou de setor: fica na linha do tempo e quem atende o novo setor é avisado
        if ($encaminhada) {
            $task->load('sector');
            $task->registrar('encaminhada', auth()->user(), null, [
                'de' => $setorAnterior?->name,
                'para' => $task->sector?->name,
            ]);
            $this->notificador->criada($task, auth()->user(), encaminhada: true);
        }

        return redirect('/tasks/'.$task->id)->with('msg', 'Tarefa atualizada com sucesso!');
    }

    public function joinTask($id)
    {
        $task = Task::with(['users', 'participantes', 'user'])->findOrFail($id);
        $user = auth()->user();

        if (! $task->estaAberta()) {
            return redirect('/tasks/'.$task->id)->with('msg', 'Esta tarefa já foi encerrada. Reabra a tarefa para assumi-la.');
        }

        if ($task->ehResponsavel($user)) {
            return redirect('/tasks/'.$task->id)->with('msg', 'Você já é responsável por esta tarefa.');
        }

        // Só assume quem atende o setor da tarefa (ou o administrador)
        abort_unless($task->podeAssumir($user), 403);

        $task->users()->syncWithoutDetaching([$user->id]);
        $task->registrar('assumiu', $user);

        if ($task->status === 'aberta') {
            $task->update(['status' => 'em_andamento']);
        }

        $this->notificador->assumida($task, $user);

        return redirect('/tasks/'.$task->id)->with('msg', 'Você agora é responsável por esta tarefa.');
    }

    /**
     * Entrega a imagem da tarefa somente para quem pode ver a tarefa.
     */
    public function imagem($id)
    {
        $task = Task::with(['users', 'participantes'])->findOrFail($id);

        abort_unless($task->visivelPara(auth()->user()) && $task->hasImage(), 404);

        return Storage::disk('local')->response('task-images/'.$task->image, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    // ------------------------------------------------------------------ Apoio

    /**
     * Nomes dos campos que mudaram na edição, para a linha do tempo.
     */
    private function camposAlterados(Task $task): array
    {
        $nomes = [
            'title' => 'título',
            'description' => 'descrição',
            'deadline' => 'prazo',
            'priority' => 'prioridade',
            'items' => 'contexto',
            'respostas' => 'dados do pedido',
            'equipment_id' => 'equipamento',
            'image' => 'imagem',
        ];

        return collect($task->getDirty())
            ->keys()
            ->map(fn ($campo) => $nomes[$campo] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Equipamentos que podem ser ligados a uma tarefa: somente da área de
     * informática (config/equipamentos.php) e que não foram baixados. O
     * equipamento já ligado à tarefa continua aparecendo na edição.
     */
    private function equipamentosDeInformatica(?int $equipamentoAtual = null)
    {
        return Equipment::with('store')
            ->where(function ($consulta) use ($equipamentoAtual) {
                $consulta->where(function ($ti) {
                    $ti->whereIn('type', config('equipamentos.tipos_informatica'))
                        ->where('status', '!=', 'baixado');
                });

                if ($equipamentoAtual) {
                    $consulta->orWhere('id', $equipamentoAtual);
                }
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Setores que aparecem como destino no formulário. Na edição, o setor atual
     * continua na lista mesmo se deixou de receber tarefas.
     */
    private function setoresDestino(?int $setorAtual = null)
    {
        return Sector::where(fn ($q) => $q->where('recebe_tarefas', true)->when($setorAtual, fn ($w) => $w->orWhere('id', $setorAtual)))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function regrasSetor(?Task $task = null): array
    {
        return [
            'sector_id' => ['nullable', 'integer', Rule::exists('sectors', 'id')->where(
                fn ($q) => $q->where('recebe_tarefas', true)->when($task?->sector_id, fn ($w) => $w->orWhere('id', $task->sector_id))
            )],
        ];
    }

    private function regrasEquipamento(?Task $task = null): array
    {
        return [
            'equipment_id' => [
                'bail',
                'nullable',
                'integer',
                function (string $atributo, mixed $valor, \Closure $falhar) use ($task) {
                    // Manter o equipamento que já estava na tarefa sempre é permitido
                    if ($task && (int) $valor === (int) $task->equipment_id) {
                        return;
                    }

                    $ehInformatica = Equipment::where('id', $valor)
                        ->whereIn('type', config('equipamentos.tipos_informatica'))
                        ->exists();

                    if (! $ehInformatica) {
                        $falhar('Escolha um equipamento de informática da lista.');
                    }
                },
            ],
        ];
    }

    /**
     * Regras dos campos fixos. Em tarefas de modelo, a descrição vira "Observações" (opcional).
     */
    private function regrasTarefa(bool $nova = false, bool $descricaoObrigatoria = true): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => [$descricaoObrigatoria ? 'required' : 'nullable', 'string', 'max:5000'],
            'deadline' => $nova ? ['required', 'date', 'after_or_equal:today'] : ['required', 'date'],
            'priority' => ['required', 'integer', Rule::in(array_keys(config('tarefas.prioridades')))],
            'items' => ['nullable', 'array'],
            'items.*' => ['string', 'max:255'],
        ];
    }

    private function mensagensTarefa(): array
    {
        return [
            'equipment_id.integer' => 'Escolha um equipamento de informática da lista.',
            'sector_id.exists' => 'Escolha um dos setores da lista.',
            'priority.in' => 'Escolha uma das prioridades da lista.',
            'deadline.after_or_equal' => 'O prazo não pode ser uma data que já passou.',
        ];
    }
}
