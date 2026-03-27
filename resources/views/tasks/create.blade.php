@extends('layouts.main')

@section('title', 'Eix Desk')

@section('content')

<div id="event-create-container" class="col-md-6 offset-md-3">
    <h1>Criar tarefa</h1>
    <form action="/tasks" method="POST">
        @csrf
        <div class="form-group">
            <label for="title">Tarefa:</label>
            <input type="text" class="form-control" id="title" name="title" placeholder="Nome da Tarefa">
        </div>
         <div class="form-group">
            <label for="title">Descrição:</label>
            <textarea name="description" id="description" class="form-control" placeholder="Descreva sua solicitação"></textarea>
        </div>
        <div class="form-group">
            <label for="deadline">Prazo:</label>
            <input type="date" class="form-control" id="deadline" name="deadline">
        </div>
         <div class="form-group">
            <label for="title">Prioridade:</label>
            <select name="priority" id="priority" class="form-control">
                <option value="0">Não</option>
                <option value="1">Sim</option>
            </select>
        </div>
        <input type="submit" class="btn btn-primary" value="Criar tarefa">
    </form>
</div>
    

@endsection