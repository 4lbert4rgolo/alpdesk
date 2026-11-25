@extends('layouts.main')

@section('title', 'AlpDesk Solutions')

@section('content')
@php
    $opcoesSituacao = ['abertas' => 'Em aberto', 'encerradas' => 'Encerradas', 'todas' => 'Todas'] + config('tarefas.status');
    $titulos = ['abertas' => 'Tarefas em aberto', 'encerradas' => 'Tarefas encerradas', 'todas' => 'Todas as tarefas'];
    $dataBr = fn ($data) => \Illuminate\Support\Carbon::parse($data)->format('d/m/Y');

    // Atalhos de período (mantêm os outros filtros)
    $hoje = today();
    $mesPassado = $hoje->copy()->subMonthNoOverflow();
    $atalhos = [
        'Hoje' => [$hoje->toDateString(), $hoje->toDateString()],
        'Últimos 7 dias' => [$hoje->copy()->subDays(6)->toDateString(), $hoje->toDateString()],
        'Últimos 30 dias' => [$hoje->copy()->subDays(29)->toDateString(), $hoje->toDateString()],
        'Este mês' => [$hoje->copy()->startOfMonth()->toDateString(), $hoje->toDateString()],
        'Mês passado' => [$mesPassado->copy()->startOfMonth()->toDateString(), $mesPassado->copy()->endOfMonth()->toDateString()],
    ];
    $urlCom = fn (array $mudancas) => request()->fullUrlWithQuery($mudancas + ['page' => null]);

    // Resumo dos filtros ativos: [texto, parâmetros para remover só aquele filtro]
    $ativos = [];

    if ($filtros['search'] !== '') {
        $ativos[] = ['Busca: "'.$filtros['search'].'"', ['search' => null]];
    }
    if ($filtros['situacao'] !== 'abertas') {
        $ativos[] = ['Situação: '.($opcoesSituacao[$filtros['situacao']] ?? $filtros['situacao']), ['situacao' => null]];
    }
    if ($filtros['de'] || $filtros['ate']) {
        if ($filtros['de'] && $filtros['ate']) {
            $periodo = $filtros['de'] === $filtros['ate'] ? 'em '.$dataBr($filtros['de']) : $dataBr($filtros['de']).' a '.$dataBr($filtros['ate']);
        } else {
            $periodo = $filtros['de'] ? 'desde '.$dataBr($filtros['de']) : 'até '.$dataBr($filtros['ate']);
        }
        $ativos[] = ['Abertas '.$periodo, ['de' => null, 'ate' => null]];
    }
    if ($filtros['responsavel'] !== '') {
        $nome = $filtros['responsavel'] === 'sem'
            ? 'sem responsável'
            : ($responsaveis->firstWhere('id', (int) $filtros['responsavel'])->name ?? 'pessoa #'.$filtros['responsavel']);
        $ativos[] = ['Responsável: '.$nome, ['responsavel' => null]];
    }
    if ($filtros['loja'] !== '') {
        $nome = $filtros['loja'] === 'sem'
            ? 'sem empresa'
            : ($lojas->firstWhere('id', (int) $filtros['loja'])->name ?? 'loja #'.$filtros['loja']);
        $ativos[] = ['Empresa: '.$nome, ['loja' => null]];
    }
    if ($filtros['prioridade']) {
        $ativos[] = ['Prioridade: '.config('tarefas.prioridades')[$filtros['prioridade']][0], ['prioridade' => null]];
    }
    if ($filtros['modelo'] !== '') {
        $nome = $filtros['modelo'] === 'livre'
            ? 'tarefa livre'
            : ($modelos->firstWhere('id', (int) $filtros['modelo'])->name ?? 'modelo #'.$filtros['modelo']);
        $ativos[] = ['Tipo: '.$nome, ['modelo' => null]];
    }

    $filtrando = count($ativos) > 0;

    // O setor funciona como "fila" (vem do menu): limpar os filtros mantém o setor escolhido
    $urlLimpar = $filtros['setor'] ? '/tarefas?setor='.$filtros['setor'] : '/tarefas';
    $painelAberto = $filtros['de'] || $filtros['ate'] || $filtros['responsavel'] !== '' || $filtros['loja'] !== '' || $filtros['modelo'] !== ''
        || $filtros['prioridade'] || $filtros['situacao'] !== 'abertas';
@endphp

<div id="search-container" class="col-md-12">
    <h1>Busque uma Tarefa</h1>
    <form action="/tarefas" method="GET" role="search" class="search-form" id="form-busca">
        <ion-icon name="search-outline"></ion-icon>
        <input type="search" id="search" name="search" value="{{ $filtros['search'] }}" placeholder="Número, título, descrição ou equipamento" aria-label="Procurar tarefa">
        <button type="submit">Buscar</button>
        @if($filtros['setor'] && $setores->count() <= 1)
            <input type="hidden" name="setor" value="{{ $filtros['setor'] }}">
        @endif
    </form>
</div>

<div id="tasks-container" class="col-md-12">
    <details class="filter-panel" {{ $painelAberto ? 'open' : '' }}>
        <summary>
            <ion-icon name="options-outline"></ion-icon>
            Filtros
            @if($filtrando)
                <span class="filter-count">{{ count($ativos) }}</span>
            @endif
        </summary>

        {{-- Os campos abaixo pertencem ao formulário da busca (form="form-busca"), então busca e filtros vão juntos --}}
        <div class="filter-grid">
            <div class="filter-field filter-periodo">
                <span class="filter-label">Período de abertura</span>
                <div class="filter-dates">
                    <label class="visually-hidden" for="de">De</label>
                    <input type="date" id="de" name="de" value="{{ $filtros['de'] }}" form="form-busca" max="{{ $hoje->toDateString() }}">
                    <span class="muted">até</span>
                    <label class="visually-hidden" for="ate">Até</label>
                    <input type="date" id="ate" name="ate" value="{{ $filtros['ate'] }}" form="form-busca" max="{{ $hoje->toDateString() }}">
                </div>
                <div class="filter-shortcuts">
                    @foreach($atalhos as $rotulo => $intervalo)
                        <a href="{{ $urlCom(['de' => $intervalo[0], 'ate' => $intervalo[1]]) }}"
                           class="{{ $filtros['de'] === $intervalo[0] && $filtros['ate'] === $intervalo[1] ? 'is-active' : '' }}">{{ $rotulo }}</a>
                    @endforeach
                </div>
            </div>

            @if($setores->count() > 1)
                <div class="filter-field">
                    <label class="filter-label" for="setor">Setor</label>
                    <select id="setor" name="setor" form="form-busca">
                        <option value="">Todos os setores</option>
                        @foreach($setores as $setor)
                            <option value="{{ $setor->id }}" {{ $filtros['setor'] === (string) $setor->id ? 'selected' : '' }}>{{ $setor->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="filter-field">
                <label class="filter-label" for="situacao">Situação</label>
                <select id="situacao" name="situacao" form="form-busca">
                    @foreach($opcoesSituacao as $chave => $rotulo)
                        <option value="{{ $chave }}" {{ $filtros['situacao'] === $chave ? 'selected' : '' }}>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label class="filter-label" for="responsavel">Responsável</label>
                <select id="responsavel" name="responsavel" form="form-busca">
                    <option value="">Qualquer pessoa</option>
                    <option value="sem" {{ $filtros['responsavel'] === 'sem' ? 'selected' : '' }}>Sem responsável</option>
                    @foreach($responsaveis as $pessoa)
                        <option value="{{ $pessoa->id }}" {{ $filtros['responsavel'] === (string) $pessoa->id ? 'selected' : '' }}>{{ $pessoa->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label class="filter-label" for="loja">Empresa</label>
                <select id="loja" name="loja" form="form-busca">
                    <option value="">Todas as empresas</option>
                    @foreach($lojas as $loja)
                        <option value="{{ $loja->id }}" {{ $filtros['loja'] === (string) $loja->id ? 'selected' : '' }}>{{ $loja->name }}</option>
                    @endforeach
                    <option value="sem" {{ $filtros['loja'] === 'sem' ? 'selected' : '' }}>Sem empresa / sem equipamento</option>
                </select>
            </div>

            @if($modelos->isNotEmpty())
                <div class="filter-field">
                    <label class="filter-label" for="modelo">Tipo de tarefa</label>
                    <select id="modelo" name="modelo" form="form-busca">
                        <option value="">Todos os tipos</option>
                        @foreach($modelos as $opcaoModelo)
                            <option value="{{ $opcaoModelo->id }}" {{ $filtros['modelo'] === (string) $opcaoModelo->id ? 'selected' : '' }}>{{ $opcaoModelo->name }}</option>
                        @endforeach
                        <option value="livre" {{ $filtros['modelo'] === 'livre' ? 'selected' : '' }}>Tarefa livre</option>
                    </select>
                </div>
            @endif

            <div class="filter-field">
                <label class="filter-label" for="prioridade">Prioridade</label>
                <select id="prioridade" name="prioridade" form="form-busca">
                    <option value="">Qualquer prioridade</option>
                    @foreach(config('tarefas.prioridades') as $nivel => $info)
                        <option value="{{ $nivel }}" {{ $filtros['prioridade'] === $nivel ? 'selected' : '' }}>{{ $info[0] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" form="form-busca" class="btn btn-primary">Aplicar filtros</button>
                @if($filtrando)
                    <a href="{{ $urlLimpar }}" class="btn btn-outline-secondary">Limpar</a>
                @endif
            </div>
        </div>
    </details>

    @if($filtrando)
        <div class="filter-active" aria-label="Filtros ativos">
            @foreach($ativos as $ativo)
                <a href="{{ $urlCom($ativo[1]) }}" class="filter-chip" title="Remover este filtro">
                    {{ $ativo[0] }} <ion-icon name="close-outline" aria-hidden="true"></ion-icon>
                </a>
            @endforeach
            <a href="{{ $urlLimpar }}" class="filter-clear">Limpar tudo</a>
        </div>
    @endif

    <div class="tasks-header">
        <div>
            <h2>
                {{ $titulos[$filtros['situacao']] ?? 'Tarefas: '.($opcoesSituacao[$filtros['situacao']] ?? '') }}
                @if($setorAtual)
                    <span class="sector-tag">{{ $setorAtual->name }}</span>
                @endif
            </h2>
            <p class="subtitle">Urgentes e de prazo mais próximo aparecem primeiro.</p>
        </div>
        <div class="tasks-header-actions">
            @if($tasks->total() > 0)
                <span class="tasks-count">{{ $tasks->total() }} {{ $tasks->total() == 1 ? 'tarefa' : 'tarefas' }}</span>
            @endif

            @can('tarefas.criar')
                @php
                    $modelosAtivos = $modelos->where('ativo', true);
                @endphp
                @if($modelosAtivos->isEmpty())
                    <a href="/tasks/create" class="btn btn-primary">
                        <ion-icon name="add-outline"></ion-icon> Nova tarefa
                    </a>
                @else
                    {{-- Botão com lista: escolhe o modelo direto daqui, sem passar pela tela de escolha --}}
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <ion-icon name="add-outline"></ion-icon> Nova tarefa
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end new-task-menu">
                            @foreach($modelosAtivos as $opcaoModelo)
                                <li><a class="dropdown-item" href="/tasks/create?modelo={{ $opcaoModelo->id }}">{{ $opcaoModelo->name }}</a></li>
                            @endforeach
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/tasks/create?livre=1">Tarefa livre</a></li>
                        </ul>
                    </div>
                @endif
            @endcan
        </div>
    </div>

    @if($tasks->total() > 0)
        <div class="task-table-wrap">
            <table class="task-table">
                <thead>
                    <tr>
                        <th scope="col">Prioridade</th>
                        <th scope="col">Tarefa</th>
                        <th scope="col">Situação</th>
                        <th scope="col">Aberta em</th>
                        <th scope="col">Prazo</th>
                        <th scope="col">Responsável</th>
                        <th scope="col"><span class="visually-hidden">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tasks as $task)
                        @php
                            $responsaveisDaTarefa = $task->users->unique('id')->pluck('name');
                        @endphp
                        <tr class="prio-row-{{ $task->priority }} {{ $task->estaAberta() ? '' : 'is-closed' }}">
                            <td data-label="Prioridade">
                                <span class="badge-priority prio-{{ $task->priority }}">{{ $task->prioridadeLabel() }}</span>
                            </td>
                            <td data-label="Tarefa" class="task-title">
                                <div>
                                    <a href="/tasks/{{ $task->id }}">#{{ $task->id }} · {{ $task->title }}</a>
                                    @if($task->template)
                                        <span class="sector-tag">{{ $task->template->name }}</span>
                                    @endif
                                    @if($task->sector && ! $setorAtual && $setores->count() > 1)
                                        <span class="sector-tag is-muted">{{ $task->sector->name }}</span>
                                    @endif
                                    @if($task->equipment)
                                        <small class="muted d-block">{{ $task->equipment->name }}{{ $task->equipment->store ? ' · '.$task->equipment->store->name : '' }}</small>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Situação">
                                <span class="badge-status status-{{ $task->status }}">{{ $task->statusLabel() }}</span>
                            </td>
                            <td data-label="Aberta em">{{ $task->created_at->format('d/m/Y') }}</td>
                            <td data-label="Prazo">
                                <span>{{ $task->deadline->format('d/m/Y') }}</span>
                                @if($task->estaVencida())
                                    <span class="deadline-late">Vencida</span>
                                @endif
                            </td>
                            <td data-label="Responsável">
                                @if($responsaveisDaTarefa->isNotEmpty())
                                    {{ $responsaveisDaTarefa->join(', ', ' e ') }}
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

        <div class="pagination-wrap">
            {{ $tasks->links('pagination::bootstrap-5') }}
        </div>
    @elseif($filtrando)
        <div class="tasks-empty">
            Nenhuma tarefa encontrada com esses filtros. <a href="{{ $urlLimpar }}">Limpar filtros</a>
        </div>
    @else
        <div class="tasks-empty">Não há tarefas pendentes.</div>
    @endif
</div>

@endsection
