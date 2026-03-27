@extends('layouts.main')

@section('title', 'Eix Desk')

@section('content')

<div id="search-container" class="col-md-12">
    <h1>Busque uma Tarefa</h1>
    <form action="">
        <input type="text" id="search" name="search" class="form-control" placeholder="procurar">
    </form>
</div>
<div id="tasks-container" class="col-md-12">
    <h2>Tarefas em andamentos</h2>
    <p class="subtitle">Verifique as tarefas que ainda não foram finalizadas</p>
    <div id="card-container" class="row">
        @foreach($tasks as $task)
        <div class="card col-md-3">
            <img src="img\placeholder.png" alt="{{ $task->title}}">
            <div class="card-body">
                <p class="card-date">10/04/2026</p>
                <h5 class="card-title">{{ $task->title }}</h5>
                <p class="card-responsible">Responsável</p>
                <a href="#" class="btn btn-primary">Visualizar</a>
            </div>
        </div>
        @endforeach
    </div>
</div>
  
@endsection