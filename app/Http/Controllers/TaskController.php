<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Equipment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    public function index(){
        $search = request('search');

        // Carrega os responsáveis junto (evita uma consulta por linha da tabela)
        // e mostra primeiro as tarefas urgentes e as de prazo mais próximo.
        $query = Task::with('users')
            ->visiveisPara(auth()->user())
            ->orderByDesc('priority')
            ->orderBy('deadline');

        if($search) {
            $tasks = $query->where('title', 'like', '%'.$search.'%')->get();
        } else {
            $tasks = $query->get();
        }

        return view('welcome', ['tasks'=> $tasks, 'search' => $search]);
    }  
    
    public function create(){
        return view('tasks.create', ['equipamentos' => $this->equipamentosDeInformatica()]);
    }

    public function store(Request $request){
        $dados = $request->validate($this->regrasTarefa() + $this->regrasEquipamento(), $this->mensagensTarefa());

        $task = new Task;

        $task->title = $dados['title'];
        $task->description = $dados['description'];
        $task->deadline = $dados['deadline'];
        $task->priority = $dados['priority'];
        $task->items = $dados['items'] ?? [];
        $task->equipment_id = $dados['equipment_id'] ?? null;

        // Imagem: somente JPG, PNG ou WEBP, guardada em pasta privada (fora de public)
        if ($request->hasFile('image')) {
            $task->image = $this->guardarImagem($request->file('image'));
        }

        $user = auth()->user();
        $task->user_id = $user->id;

        $task->save();

        // NOVIDADE: Vincula o criador automaticamente como participante/responsável
        $user->tasksAsParticipant()->attach($task->id);

        return redirect('/')->with('msg', 'Tarefa criada com sucesso!');
    }

    public function show($id){
        $task = Task::with(['user', 'users', 'equipment.store'])->findOrFail($id);

        // Quem não pode ver a tarefa recebe "não encontrada" (não revela que ela existe)
        abort_unless($task->visivelPara(auth()->user()), 404);

        return view('tasks.show', ['task' => $task]);
    }

    public function dashboard() {
        $user = auth()->user();

        // Tarefas que eu criei
        $tasks = $user->tasks;

        // Tarefas onde sou participante (Responsável)
        // ATENÇÃO: Verifique se no seu Model User o método é tasksAsParticipant (com 's')
        $tasksAsParticipant = $user->tasksAsParticipant;

        return view('tasks.dashboard', [
            'tasks' => $tasks, 
            'tasksasparticipant' => $tasksAsParticipant // Nome corrigido para a View
        ]);
    }

    public function destroy($id) {
        
        $task = Task::findOrFail($id);

        // Quem criou a tarefa (ou tem a permissão de gerenciar todas) pode excluí-la
        abort_unless($this->podeGerenciar($task), 403);

        if ($task->image) {
            Storage::disk('local')->delete('task-images/'.$task->image);
        }

        $task->users()->detach();

        $task->delete();

        return redirect('/dashboard')->with('msg', 'Tarefa excluída com sucesso!');
    }

    public function edit($id) {

        $user = auth()->user();

        $task = Task::findOrFail($id);

        if(! $this->podeGerenciar($task)){
            return redirect('/dashboard');
        }
        return view('tasks.edit', [
            'task' => $task,
            'equipamentos' => $this->equipamentosDeInformatica($task->equipment_id),
        ]);
    }

    public function update(Request $request, $id) {
        $task = Task::findOrFail($id);

        // Quem criou a tarefa (ou tem a permissão de gerenciar todas) pode editá-la
        abort_unless($this->podeGerenciar($task), 403);

        // Apenas os campos do formulário (evita alterar user_id e outros campos)
        $dados = $request->validate($this->regrasTarefa() + $this->regrasEquipamento($task), $this->mensagensTarefa());

        $data = [
            'title' => $dados['title'],
            'description' => $dados['description'],
            'deadline' => $dados['deadline'],
            'priority' => $dados['priority'],
            'items' => $dados['items'] ?? [],
            'equipment_id' => $dados['equipment_id'] ?? null,
        ];

        $imagemAntiga = null;

        if ($request->hasFile('image')) {
            $imagemAntiga = $task->image;
            $data['image'] = $this->guardarImagem($request->file('image'));
        }

        $task->update($data);

        // Remove a imagem anterior, que foi substituída
        if ($imagemAntiga) {
            Storage::disk('local')->delete('task-images/'.$imagemAntiga);
        }

        return redirect('/dashboard')->with('msg', 'Tarefa atualizada com sucesso!');
    }

    public function joinTask($id) {
        $task = Task::findOrFail($id);
        $user = auth()->user();

        $jaEraResponsavel = $task->users()->where('users.id', $user->id)->exists();

        // Evita registrar a mesma pessoa duas vezes na tarefa
        $user->tasksAsParticipant()->syncWithoutDetaching([$task->id]);

        return redirect('/dashboard')->with('msg', $jaEraResponsavel
            ? 'Você já é responsável por esta tarefa.'
            : 'Você agora é responsável por esta tarefa.');
    }

    /**
     * Quem criou a tarefa, ou quem tem a permissão "tarefas.gerenciar_todas".
     */
    private function podeGerenciar(Task $task): bool
    {
        $usuario = auth()->user();

        return $usuario
            && ((int) $usuario->id === (int) $task->user_id || $usuario->can('tarefas.gerenciar_todas'));
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

    private function mensagensEquipamento(): array
    {
        return [
            'equipment_id.integer' => 'Escolha um equipamento de informática da lista.',
        ];
    }

    /**
     * Entrega a imagem da tarefa somente para quem pode ver a tarefa.
     */
    public function imagem($id)
    {
        $task = Task::with('users')->findOrFail($id);

        abort_unless($task->visivelPara(auth()->user()) && $task->hasImage(), 404);

        return Storage::disk('local')->response('task-images/'.$task->image, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Guarda a imagem em storage/app/private/task-images com nome aleatório e
     * extensão definida pelo conteúdo do arquivo (já validado como JPG, PNG ou WEBP).
     */
    private function guardarImagem(UploadedFile $arquivo): string
    {
        return basename($arquivo->store('task-images', 'local'));
    }

    private function regrasTarefa(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'deadline' => ['required', 'date'],
            'priority' => ['required', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*' => ['string', 'max:255'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    private function mensagensTarefa(): array
    {
        return $this->mensagensEquipamento() + [
            'image.file' => 'Escolha um arquivo de imagem válido.',
            'image.mimes' => 'A imagem deve ser JPG, PNG ou WEBP.',
            'image.max' => 'A imagem pode ter no máximo 5 MB.',
            'image.uploaded' => 'Não foi possível enviar a imagem. Ela pode ser maior que o limite do servidor.',
        ];
    }
}
