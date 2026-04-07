@extends('layouts.main')

@section('title', 'Dashboard')

@section('content')

<div class="col-md-10 offset-md-1 dashboard-title-container">
    <h1>Minhas tarefas</h1>
</div>
<div class="col-md-10 offset-md-1 dashboard-tasks-container">
    @if(count($tasks) > 0)
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
                    <td scope="row">{{ $loop->index + 1}}</td>
                    <td><a href="/tasks/{{ $task->id }}"> {{ $task->title }}</a></td>
                    <td>0</td>
                    <td>
                        <a href="tasks/edit/{{ $task->id }}" class="btn btn-info edit-btn"><ion-icon name="create-outline"></ion-icon> Editar</a>
                        <form action="tasks/{{ $task->id }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger delete-btn"><ion-icon name="trash-outline"></ion-icon>Deletar</button>
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

@endsection
