@extends('layouts.main')

@section('title', 'Equipamentos')

@section('content')
@php
    $lojaAtual = $filtros['loja'] ?? null;
    $temFiltro = collect($filtros)->except('loja')->filter(fn ($valor) => $valor !== null && $valor !== '')->isNotEmpty();
    $filtrosExport = array_filter($filtros, fn ($valor) => $valor !== null && $valor !== '');
    $xlsDisponivel = class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class);
@endphp

<div id="admin-page">
    <div class="admin-header">
        <div>
            <h1>Equipamentos</h1>
            <p class="subtitle">Inventário de equipamentos, separado por loja.</p>
        </div>
        <div class="admin-actions">
            @can('equipamentos.importar')
                <a href="{{ route('equipamentos.import.form') }}" class="btn btn-outline-secondary">Importar</a>
            @endcan
            <a href="{{ route('equipamentos.export', $filtrosExport + ['formato' => 'csv']) }}" class="btn btn-outline-secondary" title="Exporta a lista com os filtros atuais">Exportar CSV</a>
            @if($xlsDisponivel)
                <a href="{{ route('equipamentos.export', $filtrosExport + ['formato' => 'xls']) }}" class="btn btn-outline-secondary" title="Exporta a lista com os filtros atuais">Exportar XLS</a>
            @endif
            @can('equipamentos.gerenciar')
                <a href="{{ route('equipamentos.create') }}" class="btn btn-primary">Novo equipamento</a>
            @endcan
        </div>
    </div>

    <nav class="store-tabs" aria-label="Lojas">
        <a href="{{ request()->fullUrlWithQuery(['loja' => null, 'page' => null]) }}"
           class="store-tab {{ $lojaAtual ? '' : 'is-active' }}">Todas <span>{{ $total }}</span></a>

        @foreach($lojas as $loja)
            <a href="{{ request()->fullUrlWithQuery(['loja' => $loja->id, 'page' => null]) }}"
               class="store-tab {{ (string) $lojaAtual === (string) $loja->id ? 'is-active' : '' }}">{{ $loja->name }} <span>{{ $loja->equipment_count }}</span></a>
        @endforeach

        @if($semLoja > 0)
            <a href="{{ request()->fullUrlWithQuery(['loja' => 'sem', 'page' => null]) }}"
               class="store-tab {{ $lojaAtual === 'sem' ? 'is-active' : '' }}">Sem loja <span>{{ $semLoja }}</span></a>
        @endif
    </nav>

    <form method="GET" action="{{ route('equipamentos.index') }}" class="filter-bar" role="search">
        @if($lojaAtual)
            <input type="hidden" name="loja" value="{{ $lojaAtual }}">
        @endif

        <input type="search" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Buscar por nome, patrimônio, usuário ou série" aria-label="Buscar equipamentos">

        <select name="setor" aria-label="Setor">
            <option value="">Todos os setores</option>
            @foreach($setores as $setor)
                <option value="{{ $setor->id }}" {{ (string) ($filtros['setor'] ?? '') === (string) $setor->id ? 'selected' : '' }}>{{ $setor->name }}</option>
            @endforeach
            <option value="sem" {{ ($filtros['setor'] ?? '') === 'sem' ? 'selected' : '' }}>Sem setor</option>
        </select>

        <select name="tipo" aria-label="Tipo">
            <option value="">Todos os tipos</option>
            @foreach($tipos as $tipo)
                <option value="{{ $tipo }}" {{ ($filtros['tipo'] ?? '') === $tipo ? 'selected' : '' }}>{{ $tipo }}</option>
            @endforeach
        </select>

        <select name="status" aria-label="Situação">
            <option value="">Qualquer situação</option>
            @foreach($situacoes as $chave => $rotulo)
                <option value="{{ $chave }}" {{ ($filtros['status'] ?? '') === $chave ? 'selected' : '' }}>{{ $rotulo }}</option>
            @endforeach
        </select>

        <select name="pendencia" aria-label="Pendências">
            <option value="">Sem filtro de pendência</option>
            <option value="sem_patrimonio" {{ ($filtros['pendencia'] ?? '') === 'sem_patrimonio' ? 'selected' : '' }}>Sem patrimônio</option>
            <option value="com_observacao" {{ ($filtros['pendencia'] ?? '') === 'com_observacao' ? 'selected' : '' }}>Com observação</option>
        </select>

        <button type="submit" class="btn btn-primary">Filtrar</button>

        @if($temFiltro)
            <a href="{{ route('equipamentos.index', array_filter(['loja' => $lojaAtual])) }}" class="btn btn-outline-secondary">Limpar</a>
        @endif
    </form>

    @if($equipamentos->total() > 0)
        <p class="list-count">{{ $equipamentos->total() }} {{ $equipamentos->total() === 1 ? 'equipamento' : 'equipamentos' }}</p>

        <div class="task-table-wrap">
            <table class="task-table">
                <thead>
                    <tr>
                        <th scope="col">Patrimônio</th>
                        <th scope="col">Equipamento</th>
                        <th scope="col">Loja</th>
                        <th scope="col">Setor</th>
                        <th scope="col">Usuário</th>
                        <th scope="col"><span class="visually-hidden">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($equipamentos as $equipamento)
                        <tr>
                            <td data-label="Patrimônio">
                                @if($equipamento->asset_tag)
                                    {{ $equipamento->asset_tag }}
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td data-label="Equipamento" class="task-title">
                                <a href="{{ route('equipamentos.show', $equipamento) }}">{{ $equipamento->name }}</a>
                                <span class="chip chip-muted">{{ $equipamento->type }}</span>
                                @if($equipamento->status !== 'em_uso')
                                    <span class="chip">{{ $equipamento->statusLabel() }}</span>
                                @endif
                            </td>
                            <td data-label="Loja">{{ $equipamento->store->name ?? 'Sem loja' }}</td>
                            <td data-label="Setor">{{ $equipamento->sector->name ?? '—' }}</td>
                            <td data-label="Usuário">{{ $equipamento->user_name ?: '—' }}</td>
                            <td class="task-action">
                                <a href="{{ route('equipamentos.show', $equipamento) }}" class="btn btn-outline-secondary btn-sm">Ver</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pager">
            {{ $equipamentos->links('pagination::bootstrap-5') }}
        </div>
    @else
        <div class="tasks-empty">Nenhum equipamento encontrado com esses filtros.</div>
    @endif
</div>
@endsection
