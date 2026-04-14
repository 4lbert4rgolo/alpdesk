<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use App\Models\User;

class TaskController extends Controller
{
    public function index(){
        $search = request('search');

        if($search) {
            $tasks = Task::where([
                ['title', 'like', '%'.$search.'%']
            ])->get();
        } else {
            $tasks = Task::all();
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
            $imageName = md5($requestImage->getClientOriginalName() . strtotime('now') . "." . $extension);
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
        $task = Task::findOrFail($id);
        $taskOwner = User::where('id', $task->user_id)->first()->toArray();

        return view('tasks.show', ['task' => $task, 'taskOwner' => $taskOwner]);
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

        $task->users()->detach();

        $task->delete();

        return redirect('/dashboard')->with('msg', 'Tarefa excluída com sucesso!');
    }

    public function edit($id) {

        $user = auth()->user();

        $task = Task::findOrFail($id);

        if($user->id != $task->user->id){
            return redirect('/dashboard');
        }
        return view ('tasks.edit', ['task' => $task]);
    }

    public function update(Request $request, $id) {
        $data = $request->all();

        if($request->hasFile('image') && $request->file('image')->isValid()){
            $requestImage = $request->image;   
            $extension = $requestImage->extension();
            $imageName = md5($requestImage->getClientOriginalName() . strtotime('now') . "." . $extension);
            $requestImage->move(public_path('img/tasks'), $imageName);
            $data['image'] = $imageName;  
        } 

        Task::findOrFail($id)->update($data);

        return redirect('/dashboard')->with('msg', 'Tarefa atualizada com sucesso!');
    }

    public function joinTask($id) {
        $user = auth()->user();
        $user->tasksAsParticipant()->attach($id);

        return redirect('/dashboard')->with('msg', 'Tarefa inicializada!');
    }
}