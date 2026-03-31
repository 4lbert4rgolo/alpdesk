@extends('layouts.main')

@section('title', $task->title)

@section('content')

    <div class="col-md-10 offset-md-1">
        <div class="row">
            <div id="image-container" class="col-md-6">
                <img src="/img/tasks/{{ $task->image }}" class="img-fluid" alt="{{ $task->title }}">
            </div>
            <div id="info-container" class="col-md-6">
                <h1>{{ $task->title }}</h1>
                <p class="task-deadline"><ion-icon name="calendar-outline"></ion-icon>{{ \Carbon\Carbon::parse($task->deadline)->format('d/m/Y') }}</p>
                <p class="task-owner"><ion-icon name="person-outline"></ion-icon>Proprietário da tarefa</p>
                <p class="task-responsible"><ion-icon name="person-outline"></ion-icon>Responsável</p>
                <a href="#" class="btn btn-primary" id="task-submit">Iniciar tarefa</a>
            </div>     
            <div class="col-md-12" id="description-container">
                <h3>Informações da tarefa</h3>
                <p class="task-description">{{ $task->description }}</p>
            </div>
        </div>         
    </div>

@endsection