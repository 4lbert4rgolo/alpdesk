@extends('layouts.main')

@section('title', 'Usuários')

@section('content')
@php
    $temFiltro = $filtros['q'] !== '' || $filtros['grupo'] !== '';
    $urlSituacao = fn ($valor) => request()->fullUrlWithQuery(['situacao' => $valor === 'todos' ? null : $valor, 'page' => null]);
@endphp

<div id="admin-page">
    <div class="admin-header">
        <div>
            <h1>Usuários</h1>
            <p class="subtitle">Crie contas, edite dados e grupos de acesso, e ative ou desative quem pode entrar no sistema.</p>
        </div>
        <a href="{{ route('admin.usuarios.create') }}" class="btn btn-primary">Novo usuário</a>
    </div>

    <nav class="store-tabs" aria-label="Situação das contas">
        <a href="{{ $urlSituacao('todos') }}" class="store-tab {{ $filtros['situacao'] === 'todos' ? 'is-active' : '' }}">Todos <span>{{ $totais['todos'] }}</span></a>
        <a href="{{ $urlSituacao('ativos') }}" class="store-tab {{ $filtros['situacao'] === 'ativos' ? 'is-active' : '' }}">Ativos <span>{{ $totais['ativos'] }}</span></a>
        <a href="{{ $urlSituacao('desativados') }}" class="store-tab {{ $filtros['situacao'] === 'desativados' ? 'is-active' : '' }}">Desativados <span>{{ $totais['desativados'] }}</span></a>
    </nav>

    <form method="GET" action="{{ route('admin.usuarios.index') }}" class="filter-bar" role="search">
        @if($filtros['situacao'] !== 'todos')
            <input type="hidden" name="situacao" value="{{ $filtros['situacao'] }}">
        @endif
        <input type="search" name="q" value="{{ $filtros['q'] }}" placeholder="Buscar por nome ou e-mail" aria-label="Buscar usuários">
        <select name="grupo" aria-label="Grupo de acesso">
            <option value="">Todos os grupos</option>
            @foreach($grupos as $grupo)
                <option value="{{ $grupo->id }}" {{ $filtros['grupo'] === (string) $grupo->id ? 'selected' : '' }}>{{ $grupo->name }}</option>
            @endforeach
            <option value="sem" {{ $filtros['grupo'] === 'sem' ? 'selected' : '' }}>Sem grupo</option>
        </select>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        @if($temFiltro)
            <a href="{{ route('admin.usuarios.index', array_filter(['situacao' => $filtros['situacao'] !== 'todos' ? $filtros['situacao'] : null])) }}" class="btn btn-outline-secondary">Limpar</a>
        @endif
    </form>

    @if($usuarios->total() > 0)
        <div class="task-table-wrap">
            <table class="task-table">
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">E-mail</th>
                        <th scope="col">Grupos</th>
                        <th scope="col">Último acesso</th>
                        <th scope="col">Situação</th>
                        <th scope="col"><span class="visually-hidden">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($usuarios as $usuario)
                        <tr class="{{ $usuario->ativo ? '' : 'is-closed' }}">
                            <td data-label="Nome" class="task-title">
                                <a href="{{ route('admin.usuarios.edit', $usuario) }}">{{ $usuario->name }}</a>
                                @if($usuario->id === auth()->id())
                                    <span class="chip chip-muted">você</span>
                                @endif
                            </td>
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
                            <td data-label="Último acesso">
                                @if($usuario->ultimo_acesso_em)
                                    <span title="{{ $usuario->ultimo_acesso_em->format('d/m/Y H:i') }}">{{ $usuario->ultimo_acesso_em->format('d/m/Y') }}</span>
                                @else
                                    <span class="muted">Nunca entrou</span>
                                @endif
                            </td>
                            <td data-label="Situação">
                                <span class="badge-status {{ $usuario->ativo ? 'status-concluida' : 'status-cancelada' }}">{{ $usuario->ativo ? 'Ativo' : 'Desativado' }}</span>
                            </td>
                            <td class="task-action">
                                <a href="{{ route('admin.usuarios.edit', $usuario) }}" class="btn btn-outline-secondary btn-sm">Editar</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pager">
            {{ $usuarios->links('pagination::bootstrap-5') }}
        </div>
    @else
        <div class="tasks-empty">Nenhum usuário encontrado com esses filtros.</div>
    @endif
</div>
@endsection
