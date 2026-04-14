@extends('layouts.main')

@section('title', 'Dashboard')

@section('content')

<div class="col-md-10 offset-md-1 dashboard-title-container">
    <h1>Minhas tarefas</h1>
</div>

<div class="col-md-10 offset-md-1 dashboard-tasks-container">
    {{-- Uso de isset e count para prevenir erro de Argument #1 ($value) must be of type Countable|array --}}
    @if(isset($tasks) && count($tasks) > 0)
    <table class="table">
        <thead>
            <tr>
                <th scope="col">Id</th>
                <th scope="col">Nome</th>
                <th scope="col">Responsável</th>
                <th scope="col">Ações</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tasks as $task)
                <tr>
                    <td scope="row">{{ $loop->index + 1 }}</td>
                    <td><a href="/tasks/{{ $task->id }}"> {{ $task->title }}</a></td>
                    <td>{{ $task->user->name }}</td>
                    <td>
                        <a href="tasks/edit/{{ $task->id }}" class="btn btn-info edit-btn">
                            <ion-icon name="create-outline"></ion-icon> Editar
                        </a>
                        <form action="tasks/{{ $task->id }}" method="POST" style="display: inline-block;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger delete-btn">
                                <ion-icon name="trash-outline"></ion-icon> Deletar
                            </button>
                        </form> 
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p>Você ainda não tem tarefas, <a href="/tasks/create">Criar tarefa</a></p>
    @endif
</div>

<div class="col-md-10 offset-md-1 dashboard-title-container">
    <h1>Tarefas que sou responsável</h1>
</div>

<div class="col-md-10 offset-md-1 dashboard-tasks-container">
    @if(isset($tasksasparticipant) && count($tasksasparticipant) > 0)
    <table class="table">
        <thead>
            <tr>
                <th scope="col">Id</th>
                <th scope="col">Nome</th>
                <th scope="col">Responsável</th>
                <th scope="col">Ações</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tasksasparticipant as $task)
                <tr>
                    <td scope="row">{{ $loop->index + 1 }}</td>
                    <td><a href="/tasks/{{ $task->id }}"> {{ $task->title }}</a></td>
                    <td>{{ $task->user->name }}</td>
                    <td>
                        <a href="#">Sair da tarefa</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p>Você não está atribuído como responsável por alguma tarefa. <a href="/">Veja as tarefas em andamento</a></p>    
    @endif
</div>

@endsection