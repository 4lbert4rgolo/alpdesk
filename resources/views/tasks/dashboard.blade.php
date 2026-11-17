@extends('layouts.main')

@section('title', 'Minhas tarefas')

@section('content')
@php
    $usuario = auth()->user();
    $abertas = $tarefas->filter(fn ($t) => $t->estaAberta());
    $total = $abertas->count();
    $urgentes = $abertas->filter(fn ($t) => $t->ehUrgente())->count();
    $vencidas = $abertas->filter(fn ($t) => $t->estaVencida())->count();
    $aguardando = $abertas->where('status', 'aguardando')->count();
    $encerradas = $tarefas->count() - $total;
    $contaAbertas = fn (array $ids) => $abertas->whereIn('id', $ids)->count();
@endphp

<div id="admin-page">
    <div class="admin-header">
        <div>
            <h1>Minhas tarefas</h1>
            <p class="subtitle">Tarefas que você abriu, pelas quais é responsável ou das quais participa.</p>
        </div>
        @can('tarefas.criar')
            <a href="/tasks/create" class="btn btn-primary">Nova tarefa</a>
        @endcan
    </div>

    @if($tarefas->isNotEmpty())
        <div class="summary-cards">
            <div class="summary-card"><strong>{{ $total }}</strong> em aberto</div>
            <div class="summary-card"><strong>{{ $urgentes }}</strong> urgentes</div>
            <div class="summary-card {{ $aguardando > 0 ? 'is-warn' : '' }}"><strong>{{ $aguardando }}</strong> aguardando</div>
            <div class="summary-card {{ $vencidas > 0 ? 'is-bad' : '' }}"><strong>{{ $vencidas }}</strong> com prazo vencido</div>
        </div>

        <div class="store-tabs" role="group" aria-label="Filtrar tarefas">
            <button type="button" class="store-tab is-active" data-filtro="aberta">Em aberto <span>{{ $total }}</span></button>
            <button type="button" class="store-tab" data-filtro="criada">Abri <span>{{ $contaAbertas($idsCriadas) }}</span></button>
            <button type="button" class="store-tab" data-filtro="responsavel">Sou responsável <span>{{ $contaAbertas($idsResponsavel) }}</span></button>
            <button type="button" class="store-tab" data-filtro="participo">Participo <span>{{ $contaAbertas($idsParticipo) }}</span></button>
            <button type="button" class="store-tab" data-filtro="urgente">Urgentes <span>{{ $urgentes }}</span></button>
            <button type="button" class="store-tab" data-filtro="vencida">Vencidas <span>{{ $vencidas }}</span></button>
            <button type="button" class="store-tab" data-filtro="encerrada">Encerradas <span>{{ $encerradas }}</span></button>
        </div>

        <div class="task-table-wrap">
            <table class="task-table">
                <thead>
                    <tr>
                        <th scope="col">Nº</th>
                        <th scope="col">Tarefa</th>
                        <th scope="col">Situação</th>
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
                            $participo = in_array($tarefa->id, $idsParticipo, true);
                            $estaAberta = $tarefa->estaAberta();
                            $responsaveis = $tarefa->users->unique('id')->pluck('name');
                        @endphp
                        <tr data-tarefa
                            data-aberta="{{ $estaAberta ? 1 : 0 }}"
                            data-encerrada="{{ $estaAberta ? 0 : 1 }}"
                            data-criada="{{ $criei && $estaAberta ? 1 : 0 }}"
                            data-responsavel="{{ $souResponsavel && $estaAberta ? 1 : 0 }}"
                            data-participo="{{ $participo && $estaAberta ? 1 : 0 }}"
                            data-urgente="{{ $tarefa->ehUrgente() && $estaAberta ? 1 : 0 }}"
                            data-vencida="{{ $tarefa->estaVencida() ? 1 : 0 }}"
                            class="prio-row-{{ $tarefa->priority }} {{ $estaAberta ? '' : 'is-closed' }}"
                            {{ $estaAberta ? '' : 'hidden' }}>
                            <td data-label="Nº">#{{ $tarefa->id }}</td>
                            <td data-label="Tarefa" class="task-title">
                                <div>
                                    <a href="/tasks/{{ $tarefa->id }}">{{ $tarefa->title }}</a>
                                    <span class="badge-priority prio-{{ $tarefa->priority }}">{{ $tarefa->prioridadeLabel() }}</span>
                                    @if($tarefa->equipment)
                                        <small class="muted d-block">{{ $tarefa->equipment->name }}</small>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Situação"><span class="badge-status status-{{ $tarefa->status }}">{{ $tarefa->statusLabel() }}</span></td>
                            <td data-label="Prazo">
                                {{ $tarefa->deadline->format('d/m/Y') }}
                                @if($tarefa->estaVencida())
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
                                    @if($participo)
                                        <span class="chip chip-muted">Participante</span>
                                    @endif
                                </span>
                            </td>
                            <td data-label="Responsáveis">{{ $responsaveis->isNotEmpty() ? $responsaveis->join(', ', ' e ') : '—' }}</td>
                            <td class="task-action">
                                <a href="/tasks/{{ $tarefa->id }}" class="btn btn-outline-secondary btn-sm">Ver</a>
                                @if($criei)
                                    <a href="/tasks/edit/{{ $tarefa->id }}" class="btn btn-outline-secondary btn-sm">Editar</a>
                                @endif
                                @if($souResponsavel && $estaAberta)
                                    <form action="/tasks/leave/{{ $tarefa->id }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Deixar de ser responsável por esta tarefa?')">
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

        <p class="tasks-empty" id="filtro-vazio" hidden>Nenhuma tarefa neste filtro.</p>

        <script>
            (function () {
                var botoes = document.querySelectorAll('[data-filtro]');
                var linhas = document.querySelectorAll('tr[data-tarefa]');
                var vazio = document.getElementById('filtro-vazio');

                botoes.forEach(function (botao) {
                    botao.addEventListener('click', function () {
                        var filtro = botao.getAttribute('data-filtro');
                        var visiveis = 0;

                        botoes.forEach(function (b) { b.classList.toggle('is-active', b === botao); });

                        linhas.forEach(function (linha) {
                            var mostrar = linha.getAttribute('data-' + filtro) === '1';
                            linha.hidden = !mostrar;
                            if (mostrar) visiveis++;
                        });

                        vazio.hidden = visiveis > 0;
                    });
                });

                if ({{ $total }} === 0) vazio.hidden = false;
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
