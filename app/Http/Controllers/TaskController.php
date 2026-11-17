<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Store;
use App\Models\Task;
use App\Models\User;
use App\Services\NotificadorTarefas;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(private NotificadorTarefas $notificador) {}

    /**
     * Lista de tarefas com busca e filtros: período de abertura, situação,
     * responsável, loja e prioridade. Tudo vai na URL, então dá para salvar
     * ou compartilhar uma consulta.
     */
    public function index(Request $request)
    {
        $filtros = $this->filtrosDaLista($request);

        $query = Task::with(['users', 'equipment.store'])
            ->visiveisPara(auth()->user());

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

        // Loja (vem do equipamento ligado à tarefa) ou "sem loja"
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

        return [
            'search' => trim((string) $request->query('search', '')),
            'situacao' => $situacao,
            'de' => $de,
            'ate' => $ate,
            'responsavel' => ($responsavel === 'sem' || ctype_digit($responsavel)) ? $responsavel : '',
            'loja' => ($loja === 'sem' || ctype_digit($loja)) ? $loja : '',
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

    public function create()
    {
        return view('tasks.create', ['equipamentos' => $this->equipamentosDeInformatica()]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate($this->regrasTarefa(true) + $this->regrasEquipamento(), $this->mensagensTarefa());
        $user = auth()->user();

        $task = new Task;
        $task->title = $dados['title'];
        $task->description = $dados['description'];
        $task->deadline = $dados['deadline'];
        $task->priority = (int) $dados['priority'];
        $task->items = $dados['items'] ?? [];
        $task->equipment_id = $dados['equipment_id'] ?? null;
        $task->status = 'aberta';
        $task->user_id = $user->id;

        // Imagem: somente JPG, PNG ou WEBP, guardada em pasta privada (fora de public)
        if ($request->hasFile('image')) {
            $task->image = $this->guardarImagem($request->file('image'));
        }

        $task->save();
        $task->registrar('criada', $user);

        // Quem abriu NÃO vira responsável automaticamente. Quem pode atender
        // tarefas pode marcar "Eu mesmo vou atender" no formulário.
        if ($request->boolean('assumir_agora') && $user->can('tarefas.assumir')) {
            $task->users()->attach($user->id);
            $task->update(['status' => 'em_andamento']);
            $task->registrar('assumiu', $user);
        } else {
            $this->notificador->criada($task, $user);
        }

        return redirect('/tasks/'.$task->id)->with('msg', "Tarefa #{$task->id} criada com sucesso!");
    }

    public function show($id)
    {
        $task = Task::with(['user', 'users', 'participantes', 'equipment.store', 'atividades.user', 'concluidaPor'])
            ->findOrFail($id);

        // Quem não pode ver a tarefa recebe "não encontrada" (não revela que ela existe)
        abort_unless($task->visivelPara(auth()->user()), 404);

        // Pessoas que podem ser adicionadas como participantes (quem ainda não está na tarefa)
        $candidatos = collect();

        if ($task->podeGerenciarParticipantes(auth()->user())) {
            $candidatos = User::orderBy('name')
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

        $task->users()->detach();
        $task->delete(); // participantes e linha do tempo saem juntos (cascade)

        return redirect('/dashboard')->with('msg', 'Tarefa excluída com sucesso!');
    }

    public function edit($id)
    {
        $task = Task::findOrFail($id);

        if (! $task->podeGerenciar(auth()->user())) {
            return redirect('/dashboard');
        }

        return view('tasks.edit', [
            'task' => $task,
            'equipamentos' => $this->equipamentosDeInformatica($task->equipment_id),
        ]);
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        // Quem criou a tarefa (ou tem a permissão de gerenciar todas) pode editá-la
        abort_unless($task->podeGerenciar(auth()->user()), 403);

        // Apenas os campos do formulário (evita alterar user_id e outros campos)
        $dados = $request->validate($this->regrasTarefa() + $this->regrasEquipamento($task), $this->mensagensTarefa());

        $data = [
            'title' => $dados['title'],
            'description' => $dados['description'],
            'deadline' => $dados['deadline'],
            'priority' => (int) $dados['priority'],
            'items' => $dados['items'] ?? [],
            'equipment_id' => $dados['equipment_id'] ?? null,
        ];

        $imagemAntiga = null;

        if ($request->hasFile('image')) {
            $imagemAntiga = $task->image;
            $data['image'] = $this->guardarImagem($request->file('image'));
        }

        $task->fill($data);
        $campos = $this->camposAlterados($task);
        $task->save();

        if ($campos) {
            $task->registrar('editada', auth()->user(), null, ['campos' => $campos]);
        }

        // Remove a imagem anterior, que foi substituída
        if ($imagemAntiga) {
            Storage::disk('local')->delete('task-images/'.$imagemAntiga);
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
     * Guarda a imagem em storage/app/private/task-images com nome aleatório e
     * extensão definida pelo conteúdo do arquivo (já validado como JPG, PNG ou WEBP).
     */
    private function guardarImagem(UploadedFile $arquivo): string
    {
        return basename($arquivo->store('task-images', 'local'));
    }

    private function regrasTarefa(bool $nova = false): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'deadline' => $nova ? ['required', 'date', 'after_or_equal:today'] : ['required', 'date'],
            'priority' => ['required', 'integer', Rule::in(array_keys(config('tarefas.prioridades')))],
            'items' => ['nullable', 'array'],
            'items.*' => ['string', 'max:255'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    private function mensagensTarefa(): array
    {
        return [
            'equipment_id.integer' => 'Escolha um equipamento de informática da lista.',
            'priority.in' => 'Escolha uma das prioridades da lista.',
            'deadline.after_or_equal' => 'O prazo não pode ser uma data que já passou.',
            'image.file' => 'Escolha um arquivo de imagem válido.',
            'image.mimes' => 'A imagem deve ser JPG, PNG ou WEBP.',
            'image.max' => 'A imagem pode ter no máximo 5 MB.',
            'image.uploaded' => 'Não foi possível enviar a imagem. Ela pode ser maior que o limite do servidor.',
        ];
    }
}
