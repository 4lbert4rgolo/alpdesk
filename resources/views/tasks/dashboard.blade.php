@extends('layouts.main')

@section('title', 'Minhas tarefas')

@section('content')
@php
    $hoje = today();
    $total = $tarefas->count();
    $urgentes = $tarefas->filter(fn ($t) => (bool) $t->priority)->count();
    $vencidas = $tarefas->filter(fn ($t) => $t->deadline->lt($hoje))->count();
    $qtdCriadas = count($idsCriadas);
    $qtdResponsavel = count($idsResponsavel);
@endphp

<div id="admin-page">
    <div class="admin-header">
        <div>
            <h1>Minhas tarefas</h1>
            <p class="subtitle">Tarefas que você abriu e tarefas pelas quais você é responsável.</p>
        </div>
        @can('tarefas.criar')
            <a href="/tasks/create" class="btn btn-primary">Nova tarefa</a>
        @endcan
    </div>

    @if($total > 0)
        <div class="summary-cards">
            <div class="summary-card"><strong>{{ $total }}</strong> em aberto</div>
            <div class="summary-card"><strong>{{ $urgentes }}</strong> urgentes</div>
            <div class="summary-card {{ $vencidas > 0 ? 'is-bad' : '' }}"><strong>{{ $vencidas }}</strong> com prazo vencido</div>
        </div>

        <div class="store-tabs" role="group" aria-label="Filtrar tarefas">
            <button type="button" class="store-tab is-active" data-filtro="todas">Todas <span>{{ $total }}</span></button>
            <button type="button" class="store-tab" data-filtro="criada">Abri <span>{{ $qtdCriadas }}</span></button>
            <button type="button" class="store-tab" data-filtro="responsavel">Sou responsável <span>{{ $qtdResponsavel }}</span></button>
            <button type="button" class="store-tab" data-filtro="urgente">Urgentes <span>{{ $urgentes }}</span></button>
            <button type="button" class="store-tab" data-filtro="vencida">Vencidas <span>{{ $vencidas }}</span></button>
        </div>

        <div class="task-table-wrap">
            <table class="task-table">
                <thead>
                    <tr>
                        <th scope="col">Nº</th>
                        <th scope="col">Tarefa</th>
                        <th scope="col">Prazo</th>
                        <th scope="col">Meu papel</th>
                        <th scope="col">Responsáveis</th>
                        <th scope="col"><span class="visually-hidden">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tarefas as $tarefa)
                        @php
                            $criei = in_array($tarefa->id, $idsCriadas, true);
                            $souResponsavel = in_array($tarefa->id, $idsResponsavel, true);
                            $vencida = $tarefa->deadline->lt($hoje);
                            $responsaveis = $tarefa->users->unique('id')->pluck('name');
                        @endphp
                        <tr data-tarefa
                            data-criada="{{ $criei ? 1 : 0 }}"
                            data-responsavel="{{ $souResponsavel ? 1 : 0 }}"
                            data-urgente="{{ $tarefa->priority ? 1 : 0 }}"
                            data-vencida="{{ $vencida ? 1 : 0 }}"
                            class="{{ $tarefa->priority ? 'is-urgent' : '' }}">
                            <td data-label="Nº">#{{ $tarefa->id }}</td>
                            <td data-label="Tarefa" class="task-title">
                                <div>
                                    <a href="/tasks/{{ $tarefa->id }}">{{ $tarefa->title }}</a>
                                    @if($tarefa->priority)
                                        <span class="badge-priority is-urgent">Urgente</span>
                                    @endif
                                    @if($tarefa->equipment)
                                        <small class="muted d-block">{{ $tarefa->equipment->name }}</small>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Prazo">
                                {{ $tarefa->deadline->format('d/m/Y') }}
                                @if($vencida)
                                    <span class="deadline-late">Vencida</span>
                                @endif
                            </td>
                            <td data-label="Meu papel">
                                <span class="chips">
                                    @if($criei)
                                        <span class="chip">Abri</span>
                                    @endif
                                    @if($souResponsavel)
                                        <span class="chip chip-muted">Responsável</span>
                                    @endif
                                </span>
                            </td>
                            <td data-label="Responsáveis">{{ $responsaveis->isNotEmpty() ? $responsaveis->join(', ', ' e ') : '—' }}</td>
                            <td class="task-action">
                                <a href="/tasks/{{ $tarefa->id }}" class="btn btn-outline-secondary btn-sm">Ver</a>
                                @if($criei)
                                    <a href="/tasks/edit/{{ $tarefa->id }}" class="btn btn-outline-secondary btn-sm">Editar</a>
                                    <form action="/tasks/{{ $tarefa->id }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Excluir esta tarefa? Essa ação não pode ser desfeita.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Excluir</button>
                                    </form>
                                @elseif($souResponsavel)
                                    <form action="/tasks/leave/{{ $tarefa->id }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Sair desta tarefa? Você deixa de ser responsável por ela.')">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">Sair</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <script>
            (function () {
                var botoes = document.querySelectorAll('[data-filtro]');
                var linhas = document.querySelectorAll('tr[data-tarefa]');

                botoes.forEach(function (botao) {
                    botao.addEventListener('click', function () {
                        var filtro = botao.getAttribute('data-filtro');

                        botoes.forEach(function (b) { b.classList.toggle('is-active', b === botao); });

                        linhas.forEach(function (linha) {
                            linha.hidden = !(filtro === 'todas' || linha.getAttribute('data-' + filtro) === '1');
                        });
                    });
                });
            })();
        </script>
    @else
        <div class="tasks-empty">
            Você ainda não tem tarefas.
            @can('tarefas.criar')
                <a href="/tasks/create">Abrir uma tarefa</a>
            @else
                <a href="/">Ver as tarefas em andamento</a>
            @endcan
        </div>
    @endif
</div>
@endsection
