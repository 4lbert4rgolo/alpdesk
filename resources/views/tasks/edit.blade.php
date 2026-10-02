@extends('layouts.main')

@section('title', 'Editando: ' . $task->title)

@section('content')

<div id="event-create-container" class="col-md-6 offset-md-3">
    <h1>Editando: {{ $task->title }}</h1>
    
    <form action="/tasks/update/{{ $task->id }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="form-group mb-3">
            <label for="title">Tarefa:</label>
            <input type="text" class="form-control" id="title" name="title" placeholder="Ex: Ajuste de permissão no ERP" value="{{ $task->title }}">
        </div>

        <div class="form-group mb-3">
            <label for="description">Descrição:</label>
            <textarea name="description" id="description" class="form-control" rows="3" placeholder="Descreva detalhadamente sua solicitação">{{ $task->description }}</textarea>
        </div>

        <div class="form-group mb-3">
            <label for="deadline">Prazo para conclusão:</label>
            <input type="date" class="form-control" id="deadline" name="deadline" value="{{date('Y-m-d', strtotime($task->deadline));}}">
        </div>

        <div class="form-group mb-3">
            <label for="priority">Prioridade Alta?</label>
            <select name="priority" id="priority" class="form-control">
                <option value="0">Não (Normal)</option>
                <option value="1" {{ $task->priority == 1 ?"selected='selected'" : "" }}>Sim (Urgente)</option>
            </select>
        </div>

        @php
            $opcoes = [
                'Problema afeta todo o setor',
                'Máquina não liga / está inoperante',
                'Autorizo acesso remoto',
                'Contém dados sensíveis / sigilosos',
                'Pode ser retirado do local',
            ];
            $marcados = is_array($task->items) ? $task->items : [];
        @endphp

        <div class="form-group mb-3">
            <label class="form-label">Contexto do problema:</label>
            @foreach($opcoes as $opcao)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="items[]" id="item-{{ $loop->index }}"
                           value="{{ $opcao }}" {{ in_array($opcao, $marcados, true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="item-{{ $loop->index }}">{{ $opcao }}</label>
                </div>
            @endforeach
        </div>

        <div class="form-group mb-4">
            <label for="image" class="form-label">Anexar imagem da solicitação:</label>
            <input type="file" id="image" name="image" class="form-control">
            @if($task->image && file_exists(public_path('img/tasks/'.$task->image)))
                <img src="/img/tasks/{{ $task->image }}" alt="{{ $task->title }}" class="img-preview">
            @endif
        </div>

        <div class="d-grid gap-2">
            <input type="submit" class="btn btn-primary btn-lg" value="Editar tarefa">
        </div>
    </form>
</div>

@endsection