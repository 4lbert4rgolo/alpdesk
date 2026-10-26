@extends('layouts.main')

@section('title', 'Grupos de acesso')

@section('content')
<div id="admin-page">
    <div class="admin-header">
        <div>
            <h1>Grupos de acesso</h1>
            <p class="subtitle">Cada grupo reúne permissões. Uma pessoa pode estar em mais de um grupo.</p>
        </div>
        <a href="{{ route('admin.grupos.create') }}" class="btn btn-primary">Novo grupo</a>
    </div>

    <div class="task-table-wrap">
        <table class="task-table">
            <thead>
                <tr>
                    <th scope="col">Grupo</th>
                    <th scope="col">Descrição</th>
                    <th scope="col">Permissões</th>
                    <th scope="col">Pessoas</th>
                    <th scope="col"><span class="visually-hidden">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach($grupos as $grupo)
                    <tr>
                        <td data-label="Grupo" class="task-title">
                            {{ $grupo->name }}
                            @if($grupo->is_system)
                                <span class="chip chip-muted">Sistema</span>
                            @endif
                        </td>
                        <td data-label="Descrição">{{ $grupo->description ?: '—' }}</td>
                        <td data-label="Permissões">{{ $grupo->isAdministrator() ? 'Todas' : count($grupo->permissions ?? []) }}</td>
                        <td data-label="Pessoas">{{ $grupo->users_count }}</td>
                        <td class="task-action">
                            <a href="{{ route('admin.grupos.edit', $grupo) }}" class="btn btn-outline-secondary btn-sm">Editar</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
