@extends('layouts.main')

@section('title', 'Dashboard')

@section('content')

<div class="col-md-10 offset-md-1 dashboard-title-container">
    <h1>Minhas tarefas</h1>
</div>
<div class="col-md-10 offset-md-1 dashboard-tasks-container">
    @if(count($tasks) > 0)
    @else
    <p>Você ainda não tem tarefas, <a href="/tasks/create">Criar tarefa</a></p>
    @endif
</div>

@endsection
