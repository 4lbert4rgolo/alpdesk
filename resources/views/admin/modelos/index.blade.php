@extends('layouts.main')

@section('title', 'Modelos de tarefa')

@section('content')
<div id="admin-page">
    <div class="admin-header">
        <div>
            <h1>Modelos de tarefa</h1>
            <p class="subtitle">Formulários prontos para pedidos que se repetem (ex.: Devolução de valores). Quem abre a tarefa escolhe o modelo e preenche os campos.</p>
        </div>
        <a href="{{ route('admin.modelos.create') }}" class="btn btn-primary">Novo modelo</a>
    </div>

    @if($modelos->isNotEmpty())
        <div class="task-table-wrap">
            <table class="task-table">
                <thead>
                    <tr>
                        <th scope="col">Modelo</th>
                        <th scope="col">Setor</th>
                        <th scope="col">Campos</th>
                        <th scope="col">Tarefas abertas com ele</th>
                        <th scope="col">Situação</th>
                        <th scope="col"><span class="visually-hidden">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($modelos as $modelo)
                        <tr class="{{ $modelo->ativo ? '' : 'is-closed' }}">
                            <td data-label="Modelo" class="task-title">
                                <a href="{{ route('admin.modelos.edit', $modelo) }}">{{ $modelo->name }}</a>
                                @if($modelo->descricao)
                                    <small class="muted d-block">{{ $modelo->descricao }}</small>
                                @endif
                            </td>
                            <td data-label="Setor">{{ $modelo->sector->name ?? 'Quem abre escolhe' }}</td>
                            <td data-label="Campos">{{ count($modelo->campos ?? []) }}</td>
                            <td data-label="Tarefas abertas com ele">{{ $modelo->tasks_count }}</td>
                            <td data-label="Situação">
                                <span class="badge-status {{ $modelo->ativo ? 'status-concluida' : 'status-cancelada' }}">{{ $modelo->ativo ? 'Ativo' : 'Desativado' }}</span>
                            </td>
                            <td class="task-action">
                                <div class="row-actions">
                                    <a href="{{ route('admin.modelos.edit', $modelo) }}" class="btn btn-outline-secondary btn-sm">
                                        <ion-icon name="create-outline"></ion-icon> Editar
                                    </a>
                                    <form method="POST" action="{{ route('admin.modelos.destroy', $modelo) }}"
                                          onsubmit="return confirm('Remover o modelo {{ addslashes($modelo->name) }}? As {{ $modelo->tasks_count }} tarefa(s) abertas com ele continuam como estão.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <ion-icon name="trash-outline"></ion-icon> Remover
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="tasks-empty">Nenhum modelo ainda. <a href="{{ route('admin.modelos.create') }}">Crie o primeiro</a>.</div>
    @endif
</div>
@endsection
