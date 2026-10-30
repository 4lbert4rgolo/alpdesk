@extends('layouts.main')

@section('title', 'Painel - AlpDesk')

@section('content')
@php
    $plural = fn ($quantidade, $singular, $plural) => $quantidade === 1 ? $singular : $plural;

    // Gráfico de colunas (SVG): uma coluna por dia
    $larguraColuna = 36;
    $alturaGrafico = 120;
    $quantidadeDias = count($serie);
    $maximoSerie = max(1, collect($serie)->max('qtd'));
    $passoRotulo = $quantidadeDias <= 14 ? 1 : 5;
@endphp

<div id="admin-page">
    <div class="admin-header">
        <div>
            <h1>Painel</h1>
            <p class="subtitle">Visão geral das tarefas e dos equipamentos.</p>
        </div>

        <div class="store-tabs" role="group" aria-label="Período">
            @foreach([7, 14, 30] as $opcao)
                <a href="{{ route('painel', ['dias' => $opcao]) }}" class="store-tab {{ $dias === $opcao ? 'is-active' : '' }}">{{ $opcao }} dias</a>
            @endforeach
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi"><strong>{{ $kpis['total'] }}</strong><span>tarefas em andamento</span></div>
        <div class="kpi"><strong>{{ $kpis['urgentes'] }}</strong><span>urgentes</span></div>
        <div class="kpi {{ $kpis['vencidas'] > 0 ? 'is-bad' : '' }}"><strong>{{ $kpis['vencidas'] }}</strong><span>com prazo vencido</span></div>
        <div class="kpi"><strong>{{ $kpis['vencem7'] }}</strong><span>vencem em 7 dias</span></div>
        <div class="kpi {{ $kpis['sem_responsavel'] > 0 ? 'is-warn' : '' }}"><strong>{{ $kpis['sem_responsavel'] }}</strong><span>aguardando alguém assumir</span></div>
        <div class="kpi"><strong>{{ $kpis['no_periodo'] }}</strong><span>abertas nos últimos {{ $dias }} dias</span></div>
    </div>

    <div class="dash-grid">
        <section class="dash-card span-8">
            <h2>Tarefas abertas por dia</h2>
            <svg class="chart" viewBox="0 0 {{ $quantidadeDias * $larguraColuna }} {{ $alturaGrafico + 22 }}" role="img" aria-label="Tarefas abertas por dia nos últimos {{ $dias }} dias">
                <line class="chart-base" x1="0" y1="{{ $alturaGrafico }}" x2="{{ $quantidadeDias * $larguraColuna }}" y2="{{ $alturaGrafico }}"></line>
                @foreach($serie as $indice => $dia)
                    @php
                        $altura = round($dia['qtd'] / $maximoSerie * ($alturaGrafico - 16), 1);
                        $centro = $indice * $larguraColuna + $larguraColuna / 2;
                    @endphp
                    @if($dia['qtd'] > 0)
                        <rect class="chart-bar" x="{{ $indice * $larguraColuna + 6 }}" y="{{ $alturaGrafico - $altura }}" width="{{ $larguraColuna - 12 }}" height="{{ $altura }}" rx="3">
                            <title>{{ $dia['rotulo'] }}: {{ $dia['qtd'] }} {{ $plural($dia['qtd'], 'tarefa', 'tarefas') }}</title>
                        </rect>
                        <text class="chart-value" x="{{ $centro }}" y="{{ $alturaGrafico - $altura - 4 }}">{{ $dia['qtd'] }}</text>
                    @endif
                    @if($indice % $passoRotulo === 0)
                        <text class="chart-label" x="{{ $centro }}" y="{{ $alturaGrafico + 14 }}">{{ $dia['curto'] }}</text>
                    @endif
                @endforeach
            </svg>
        </section>

        <section class="dash-card span-4">
            <h2>Próximos vencimentos</h2>
            @if($vencimentos->isNotEmpty())
                <ul class="rank">
                    @foreach($vencimentos as $tarefa)
                        @php
                            $diferenca = (int) $hoje->diffInDays($tarefa->deadline, false);
                            $prazo = $diferenca < 0
                                ? 'Vencida há '.abs($diferenca).' '.$plural(abs($diferenca), 'dia', 'dias')
                                : ($diferenca === 0 ? 'Vence hoje' : 'Vence em '.$diferenca.' '.$plural($diferenca, 'dia', 'dias'));
                        @endphp
                        <li>
                            <div>
                                <a href="/tasks/{{ $tarefa->id }}">{{ $tarefa->title }}</a>
                                <small class="muted d-block">#{{ $tarefa->id }} · {{ $tarefa->deadline->format('d/m/Y') }}</small>
                            </div>
                            <span class="{{ $diferenca < 0 ? 'deadline-late' : 'muted' }}">{{ $prazo }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="muted">Nenhuma tarefa vence nos próximos 7 dias.</p>
            @endif
        </section>

        <section class="dash-card span-6">
            <h2>Tarefas por loja <small>(pela loja do equipamento)</small></h2>
            @include('painel._barras', ['itens' => $porLoja, 'vazio' => 'Ainda não há tarefas.'])
        </section>

        <section class="dash-card span-6">
            <h2>Tarefas por tipo de equipamento</h2>
            @include('painel._barras', ['itens' => $porTipo, 'vazio' => 'Nenhuma tarefa está ligada a um equipamento.'])
        </section>

        <section class="dash-card span-6">
            <h2>Equipamentos com mais tarefas</h2>
            @if($topEquipamentos->isNotEmpty())
                <ol class="rank">
                    @foreach($topEquipamentos as $linha)
                        @php $equipamento = $linha['equipamento']; @endphp
                        <li>
                            <div>
                                @can('equipamentos.ver')
                                    <a href="{{ route('equipamentos.show', $equipamento) }}">{{ $equipamento->name }}</a>
                                @else
                                    <strong>{{ $equipamento->name }}</strong>
                                @endcan
                                <small class="muted d-block">{{ $equipamento->asset_tag ?: 'sem patrimônio' }} · {{ $equipamento->store->name ?? 'Sem loja' }}</small>
                            </div>
                            <span class="chip">{{ $linha['qtd'] }} {{ $plural($linha['qtd'], 'tarefa', 'tarefas') }}</span>
                        </li>
                    @endforeach
                </ol>
            @else
                <p class="muted">Nenhuma tarefa está ligada a um equipamento.</p>
            @endif
        </section>

        <section class="dash-card span-6">
            <h2>Tarefas por técnico</h2>
            @include('painel._barras', ['itens' => $carga, 'vazio' => 'Nenhum técnico assumiu tarefas ainda.'])
        </section>

        @if($inventario)
            <section class="dash-card span-12">
                <h2>Inventário de equipamentos por loja</h2>
                <div class="chips inventory-stats">
                    <span class="chip">{{ $inventario['total'] }} equipamentos</span>
                    <span class="chip">{{ $inventario['informatica'] }} de informática</span>
                    <span class="chip chip-muted">{{ $inventario['sem_patrimonio'] }} sem patrimônio</span>
                    <span class="chip chip-muted">{{ $inventario['sem_loja'] }} sem loja</span>
                </div>
                @include('painel._barras', ['itens' => $inventario['lojas'], 'vazio' => 'Nenhum equipamento cadastrado.'])
            </section>
        @endif
    </div>
</div>
@endsection
