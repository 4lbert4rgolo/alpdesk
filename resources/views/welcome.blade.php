@extends('layouts.main')

@section('title', 'AlpDesk Solutions')

@section('content')

<div id="search-container" class="col-md-12">
    <h1>Busque uma Tarefa</h1>
    <form action="/" method="GET" role="search" class="search-form">
        <ion-icon name="search-outline"></ion-icon>
        <input type="search" id="search" name="search" value="{{ $search }}" placeholder="Procurar tarefa" aria-label="Procurar tarefa">
        <button type="submit">Buscar</button>
    </form>
</div>

<div id="tasks-container" class="col-md-12">
    <div class="tasks-header">
        <div>
            @if($search)
                <h2>Buscando por: {{ $search }}</h2>
            @else
                <h2>Tarefas em andamento</h2>
            @endif
            <p class="subtitle">Verifique as tarefas que ainda não foram finalizadas</p>
        </div>
        @if(count($tasks) > 0)
            <span class="tasks-count">{{ count($tasks) }} {{ count($tasks) == 1 ? 'tarefa' : 'tarefas' }}</span>
        @endif
    </div>

    @if(count($tasks) > 0)
        <div class="task-table-wrap">
            <table class="task-table">
                <thead>
                    <tr>
                        <th scope="col">Prioridade</th>
                        <th scope="col">Tarefa</th>
                        <th scope="col">Prazo</th>
                        <th scope="col">Responsável</th>
                        <th scope="col"><span class="visually-hidden">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tasks as $task)
                        @php
                            $responsaveis = $task->users->unique('id')->pluck('name');
                            $vencida = $task->deadline->lt(today());
                        @endphp
                        <tr class="{{ $task->priority ? 'is-urgent' : '' }}">
                            <td data-label="Prioridade">
                                <span class="badge-priority {{ $task->priority ? 'is-urgent' : '' }}">
                                    {{ $task->priority ? 'Urgente' : 'Normal' }}
                                </span>
                            </td>
                            <td data-label="Tarefa" class="task-title">
                                <a href="/tasks/{{ $task->id }}">{{ $task->title }}</a>
                            </td>
                            <td data-label="Prazo">
                                <span>{{ $task->deadline->format('d/m/Y') }}</span>
                                @if($vencida)
                                    <span class="deadline-late">Vencida</span>
                                @endif
                            </td>
                            <td data-label="Responsável">
                                @if($responsaveis->isNotEmpty())
                                    {{ $responsaveis->join(', ', ' e ') }}
                                @else
                                    <span class="muted">Sem responsável</span>
                                @endif
                            </td>
                            <td class="task-action">
                                <a href="/tasks/{{ $task->id }}" class="btn btn-primary btn-sm">Visualizar</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif($search)
        <div class="tasks-empty">
            Não foi possível encontrar nenhuma tarefa com "{{ $search }}". <a href="/">Ver todas</a>
        </div>
    @else
        <div class="tasks-empty">Não há tarefas pendentes.</div>
    @endif
</div>

@endsection
