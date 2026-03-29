@extends('layouts.main')

@section('title', 'Criar Tarefa - AlpDesk')

@section('content')

<div id="event-create-container" class="col-md-6 offset-md-3">
    <h1>Criar Nova Tarefa</h1>
    
    <form action="/tasks" method="POST" enctype="multipart/form-data">
        @csrf
        
        <div class="form-group mb-3">
            <label for="title">Tarefa:</label>
            <input type="text" class="form-control" id="title" name="title" placeholder="Ex: Ajuste de permissão no ERP">
        </div>

        <div class="form-group mb-3">
            <label for="description">Descrição:</label>
            <textarea name="description" id="description" class="form-control" rows="3" placeholder="Descreva detalhadamente sua solicitação"></textarea>
        </div>

        <div class="form-group mb-3">
            <label for="deadline">Prazo para conclusão:</label>
            <input type="date" class="form-control" id="deadline" name="deadline">
        </div>

        <div class="form-group mb-3">
            <label for="priority">Prioridade Alta?</label>
            <select name="priority" id="priority" class="form-control">
                <option value="0">Não (Normal)</option>
                <option value="1">Sim (Urgente)</option>
            </select>
        </div>

        <div class="form-group mb-4">
            <label for="image" class="form-label">Anexar imagem da solicitação:</label>
            <input type="file" id="image" name="image" class="form-control">
        </div>

        <div class="d-grid gap-2">
            <input type="submit" class="btn btn-primary btn-lg" value="Criar tarefa">
        </div>
    </form>
</div>

@endsection