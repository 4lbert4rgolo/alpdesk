@extends('layouts.main')

@section('title', 'Nova tarefa - AlpDesk')

@section('content')
<div id="admin-page" class="admin-narrow">
    <a href="/tarefas" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar às tarefas
    </a>

    <h1 class="admin-title">Nova tarefa{{ $modelo ? ' · '.$modelo->name : '' }}</h1>
    @if($modelo)
        <p class="subtitle"><a href="/tasks/create">Escolher outro modelo</a></p>
    @endif

    @include('tasks._form', ['task' => null])
</div>
@endsection
