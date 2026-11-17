@extends('layouts.main')

@section('title', 'Nova tarefa - AlpDesk')

@section('content')
<div id="admin-page" class="admin-narrow">
    <a href="/" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar às tarefas
    </a>

    <h1 class="admin-title">Nova tarefa</h1>

    @include('tasks._form', ['task' => null])
</div>
@endsection
