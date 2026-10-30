@extends('layouts.main')

@section('title', 'Editar tarefa - AlpDesk')

@section('content')
<div id="admin-page" class="admin-narrow">
    <a href="/tasks/{{ $task->id }}" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar para a tarefa
    </a>

    <h1 class="admin-title">Editar tarefa #{{ $task->id }}</h1>

    @include('tasks._form', ['task' => $task])
</div>
@endsection
