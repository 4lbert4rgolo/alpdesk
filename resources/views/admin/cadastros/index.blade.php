@extends('layouts.main')

@section('title', $config['titulo'])

@section('content')
<div id="admin-page">
    <div class="admin-header">
        <div>
            <h1>Setores e empresas</h1>
            <p class="subtitle">Usados no cadastro de usuários e de equipamentos. Cada pessoa tem um setor e uma empresa. Marque <strong>Recebe tarefas</strong> nos setores que atendem pedidos (ex.: TI, Financeiro).
                @if($tipo === 'empresas')
                    <br>Em cada empresa, clique em <strong>Editar dados</strong> e preencha razão social, CNPJ, endereço e o Encarregado: eles aparecem nos Termos de Uso e na Política de Privacidade de quem trabalha nela.
                @endif
            </p>
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
        <input type="text" name="name" maxlength="100" required placeholder="{{ $tipo === 'empresas' ? 'Nome fantasia' : 'Nome' }}" aria-label="{{ $config['novo'] }}">
        @if($tipo === 'setores')
            <label class="check-inline"><input type="checkbox" name="recebe_tarefas" value="1"> Recebe tarefas</label>
        @endif
        <button type="submit" class="btn btn-primary">{{ $config['novo'] }}</button>
    </form>

    @if($itens->isNotEmpty())
        <div class="task-table-wrap">
            <table class="task-table">
                <thead>
                    <tr>
                        <th scope="col">{{ $tipo === 'empresas' ? 'Nome fantasia' : 'Nome' }}</th>
                        <th scope="col">Pessoas</th>
                        <th scope="col">Equipamentos</th>
                        @if($tipo === 'setores')
                            <th scope="col">Tarefas</th>
                        @endif
                        <th scope="col"><span class="visually-hidden">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($itens as $item)
                        <tr>
                            <td data-label="Nome">
                                @if($tipo === 'empresas')
                                    {{-- Empresas: os dados completos ficam numa tela própria ("Editar dados") --}}
                                    <a href="{{ route('admin.cadastros.edit', [$tipo, $item->id]) }}" class="empresa-nome">{{ $item->name }}</a>
                                    @if($item->ehLocal())
                                        <small class="muted d-block">
                                            Local · termo LGPD usa {{ $item->empresaResponsavel?->name ?? 'os dados padrão do .env' }}
                                        </small>
                                        <span class="chip chip-muted empresa-status">Local (sem CNPJ)</span>
                                    @else
                                    <small class="muted d-block">
                                        {{ $item->razao_social ?: 'Razão social não informada' }}{{ $item->cnpj ? ' · CNPJ '.$item->cnpj : '' }}
                                    </small>
                                    @endif
                                    @if($item->ehLocal())
                                        {{-- local não precisa de dados próprios --}}
                                    @elseif($item->dadosLgpdCompletos())
                                        <span class="chip chip-ok empresa-status">Dados completos</span>
                                    @else
                                        <span class="chip chip-warn empresa-status">Faltam dados para o termo LGPD</span>
                                    @endif
                                @else
                                    <form method="POST" action="{{ route('admin.cadastros.update', [$tipo, $item->id]) }}" class="inline-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name" value="{{ $item->name }}" maxlength="100" required aria-label="Nome de {{ $item->name }}">
                                        <label class="check-inline" title="Aparece como destino ao abrir uma tarefa">
                                            <input type="checkbox" name="recebe_tarefas" value="1" {{ $item->recebe_tarefas ? 'checked' : '' }}> Recebe tarefas
                                        </label>
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">Salvar</button>
                                    </form>
                                @endif
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
                            @if($tipo === 'setores')
                                <td data-label="Tarefas">
                                    <a href="/tarefas?setor={{ $item->id }}&situacao=todas">{{ $item->tasks_count }}</a>
                                </td>
                            @endif
                            <td class="task-action">
                                @if($tipo === 'empresas')
                                    <a href="{{ route('admin.cadastros.edit', [$tipo, $item->id]) }}" class="btn btn-outline-secondary btn-sm">Editar dados</a>
                                @endif
                                @php
                                    $vinculos = $item->users_count + $item->equipment_count + ($item->tasks_count ?? 0);
                                    $outros = $itens->where('id', '!=', $item->id);
                                @endphp
                                @if($vinculos === 0)
                                    <form method="POST" action="{{ route('admin.cadastros.destroy', [$tipo, $item->id]) }}"
                                          onsubmit="return confirm('Excluir {{ addslashes($item->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Excluir</button>
                                    </form>
                                @else
                                    <details class="delete-box">
                                        <summary class="btn btn-outline-danger btn-sm">Excluir</summary>
                                        <form method="POST" action="{{ route('admin.cadastros.destroy', [$tipo, $item->id]) }}"
                                              onsubmit="return confirm('Excluir {{ addslashes($item->name) }} e mover os vínculos?')">
                                            @csrf
                                            @method('DELETE')
                                            <label for="mover-{{ $item->id }}">
                                                Em uso por {{ $item->users_count }} pessoa(s), {{ $item->equipment_count }} equipamento(s){{ isset($item->tasks_count) ? ' e '.$item->tasks_count.' tarefa(s)' : '' }}. Mover para:
                                            </label>
                                            <select id="mover-{{ $item->id }}" name="mover_para">
                                                <option value="">Deixar {{ $tipo === 'setores' ? 'sem setor' : 'sem empresa' }}</option>
                                                @foreach($outros as $outro)
                                                    <option value="{{ $outro->id }}">{{ $outro->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-danger btn-sm">Confirmar exclusão</button>
                                        </form>
                                    </details>
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
