<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use App\Models\User;

class TaskController extends Controller
{
    public function index(){
        $search = request('search');

        // Carrega os responsáveis junto (evita uma consulta por linha da tabela)
        // e mostra primeiro as tarefas urgentes e as de prazo mais próximo.
        $query = Task::with('users')
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
        return view('tasks.create');
    }

    public function store(Request $request){
        $task = new Task;

        $task->title = $request->title;
        $task->description = $request->description;
        $task->deadline = $request->deadline;
        $task->priority = $request->priority;
        $task->items = $request->items ?? [];

        // Image Upload
        if($request->hasFile('image') && $request->file('image')->isValid()){
            $requestImage = $request->image;   
            $extension = $requestImage->extension();
            $imageName = md5($requestImage->getClientOriginalName() . strtotime('now')) . '.' . $extension;
            $requestImage->move(public_path('img/tasks'), $imageName);
            $task->image = $imageName;    
        }

        $user = auth()->user();
        $task->user_id = $user->id;

        $task->save();

        // NOVIDADE: Vincula o criador automaticamente como participante/responsável
        $user->tasksAsParticipant()->attach($task->id);

        return redirect('/')->with('msg', 'Tarefa criada com sucesso!');
    }

    public function show($id){
        $task = Task::with(['user', 'users'])->findOrFail($id);

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

        // Somente quem criou a tarefa pode excluí-la
        abort_unless((int) auth()->id() === (int) $task->user_id, 403);

        $task->users()->detach();

        $task->delete();

        return redirect('/dashboard')->with('msg', 'Tarefa excluída com sucesso!');
    }

    public function edit($id) {

        $user = auth()->user();

        $task = Task::findOrFail($id);

        if($user->id !== (int) $task->user_id){
            return redirect('/dashboard');
        }
        return view ('tasks.edit', ['task' => $task]);
    }

    public function update(Request $request, $id) {
        $task = Task::findOrFail($id);

        // Somente quem criou a tarefa pode editá-la
        abort_unless((int) auth()->id() === (int) $task->user_id, 403);

        // Apenas os campos do formulário (evita alterar user_id e outros campos)
        $data = $request->only(['title', 'description', 'deadline', 'priority']);
        $data['items'] = $request->input('items', []);

        if($request->hasFile('image') && $request->file('image')->isValid()){
            $requestImage = $request->image;   
            $extension = $requestImage->extension();
            $imageName = md5($requestImage->getClientOriginalName() . strtotime('now')) . '.' . $extension;
            $requestImage->move(public_path('img/tasks'), $imageName);
            $data['image'] = $imageName;  
        } 

        $task->update($data);

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
}