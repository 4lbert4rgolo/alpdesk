@extends('layouts.main')

@section('title', 'Usuários')

@section('content')
<div id="admin-page">
    <div class="admin-header">
        <div>
            <h1>Usuários</h1>
            <p class="subtitle">Crie contas e defina a quais grupos de acesso cada pessoa pertence.</p>
        </div>
        <a href="{{ route('admin.usuarios.create') }}" class="btn btn-primary">Novo usuário</a>
    </div>

    <div class="task-table-wrap">
        <table class="task-table">
            <thead>
                <tr>
                    <th scope="col">Nome</th>
                    <th scope="col">E-mail</th>
                    <th scope="col">Grupos</th>
                    <th scope="col"><span class="visually-hidden">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach($usuarios as $usuario)
                    <tr>
                        <td data-label="Nome" class="task-title">{{ $usuario->name }}</td>
                        <td data-label="E-mail">{{ $usuario->email }}</td>
                        <td data-label="Grupos">
                            @if($usuario->accessGroups->isNotEmpty())
                                <span class="chips">
                                    @foreach($usuario->accessGroups as $grupo)
                                        <span class="chip">{{ $grupo->name }}</span>
                                    @endforeach
                                </span>
                            @else
                                <span class="muted">Sem grupo (sem permissões)</span>
                            @endif
                        </td>
                        <td class="task-action">
                            <a href="{{ route('admin.usuarios.edit', $usuario) }}" class="btn btn-outline-secondary btn-sm">Editar</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
