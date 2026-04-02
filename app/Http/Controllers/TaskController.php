<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Task;

class TaskController extends Controller
    {
        public function index(){

            $tasks = Task::all();

            return view('welcome', ['tasks'=> $tasks]);
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
            $task->items = $request->items;

            // Image Upload

            if($request->hasFile('image') && $request->file('image')->isValid()){

                $requestImage = $request->image;   

                $extension = $requestImage->extension();

                $imageName = md5($requestImage->getClientOriginalName() . strtotime('now') . "." . $extension);

                $requestImage->move(public_path('img/tasks'), $imageName);

                $task->image = $imageName;    

            }

            $task->save();

            return redirect('/')->with('msg', 'Tarefa criada com sucesso!');

        }

        public function show($id){

            $task = Task::findOrFail($id);

            return view('tasks.show', ['task' => $task]);

        }
    }

