@extends('layouts.main')

@section('title', $config['titulo'])

@section('content')
<div id="admin-page">
    <div class="admin-header">
        <div>
            <h1>Setores e lojas</h1>
            <p class="subtitle">Usados no cadastro de usuários e de equipamentos. Cada pessoa tem um setor e uma loja.</p>
        </div>
    </div>

    <nav class="store-tabs" aria-label="Tipo de cadastro">
        @foreach($tipos as $chave => $opcao)
            <a href="{{ route('admin.cadastros.index', $chave) }}" class="store-tab {{ $tipo === $chave ? 'is-active' : '' }}">{{ $opcao['titulo'] }}</a>
        @endforeach
    </nav>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.cadastros.store', $tipo) }}" class="filter-bar">
        @csrf
        <input type="text" name="name" maxlength="100" required placeholder="Nome" aria-label="{{ $config['novo'] }}">
        <button type="submit" class="btn btn-primary">{{ $config['novo'] }}</button>
    </form>

    @if($itens->isNotEmpty())
        <div class="task-table-wrap">
            <table class="task-table">
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Pessoas</th>
                        <th scope="col">Equipamentos</th>
                        <th scope="col"><span class="visually-hidden">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($itens as $item)
                        <tr>
                            <td data-label="Nome">
                                <form method="POST" action="{{ route('admin.cadastros.update', [$tipo, $item->id]) }}" class="inline-form">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="name" value="{{ $item->name }}" maxlength="100" required aria-label="Nome de {{ $item->name }}">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Renomear</button>
                                </form>
                            </td>
                            <td data-label="Pessoas">
                                <a href="{{ route('admin.usuarios.index', [$config['filtro'] => $item->id]) }}">{{ $item->users_count }}</a>
                            </td>
                            <td data-label="Equipamentos">
                                @can('equipamentos.ver')
                                    <a href="{{ route('equipamentos.index', [$config['filtro'] => $item->id]) }}">{{ $item->equipment_count }}</a>
                                @else
                                    {{ $item->equipment_count }}
                                @endcan
                            </td>
                            <td class="task-action">
                                @if($item->users_count === 0 && $item->equipment_count === 0)
                                    <form method="POST" action="{{ route('admin.cadastros.destroy', [$tipo, $item->id]) }}"
                                          onsubmit="return confirm('Excluir {{ addslashes($item->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Excluir</button>
                                    </form>
                                @else
                                    <span class="muted" title="Só dá para excluir o que não está em uso">Em uso</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="tasks-empty">Nenhum cadastro ainda.</div>
    @endif
</div>
@endsection
