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

            return redirect('/')->with('msg', 'Tarefa criada com sucesso!');

        }

        public function show($id){

            $task = Task::findOrFail($id);

            $taskOwner = User::where('id', $task->user_id)->first()->toArray();

            return view('tasks.show', ['task' => $task, 'taskOwner' => $taskOwner]);

        }

        public function dashboard() {

            $user = auth()->user();

            $tasks = $user->tasks;

            return view('tasks.dashboard', ['tasks' => $tasks]);

        }

        public function destroy($id) {

            Task::findOrFail($id)->delete();

            return redirect('/dashboard')->with('msg', 'Tarefa excluída com sucesso!');

        }

        public function edit($id) {

           $task = Task::findOrFail($id);

           return view ('tasks.edit', ['task' => $task]);

        }

        public function update(Request $request, $id) {

            $data = $request->all();

            // Image Upload

            if($request->hasFile('image') && $request->file('image')->isValid()){

                $requestImage = $request->image;   

                $extension = $requestImage->extension();

                $imageName = md5($requestImage->getClientOriginalName() . strtotime('now') . "." . $extension);

                $requestImage->move(public_path('img/tasks'), $imageName);

                $data['image'] = $imageName;  

            } 

            Task::findOrFail($request->id)->update($data);

            return redirect('/dashboard')->with('msg', 'Tarefa atualizada com sucesso!');

        }

    }
