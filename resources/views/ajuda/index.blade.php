@extends('layouts.main')

@section('title', 'Central de Ajuda')

@section('content')
@php
    $podeGerenciar = auth()->user()->can('faq.gerenciar');
    $totalGeral = $contagem->sum();
    $filtrando = $busca !== '' || $categoria !== '';
@endphp

<div id="admin-page" class="help-page">
    <div class="admin-header">
        <div>
            <h1>Central de Ajuda</h1>
            <p class="subtitle">Perguntas frequentes, tutoriais, circulares e documentos da empresa.</p>
        </div>
        @if($podeGerenciar)
            <div class="admin-actions">
                <a href="{{ route('ajuda.create', array_filter(['tipo' => $tipo])) }}" class="btn btn-primary">
                    <ion-icon name="add-outline"></ion-icon> Novo conteúdo
                </a>
            </div>
        @endif
    </div>

    {{-- Circulares que a pessoa ainda precisa confirmar --}}
    @if($pendentes->isNotEmpty())
        <div class="alert alert-warning help-pending" role="status">
            <strong><ion-icon name="megaphone-outline"></ion-icon>
                {{ $pendentes->count() === 1 ? 'Você tem 1 circular para confirmar a leitura:' : "Você tem {$pendentes->count()} circulares para confirmar a leitura:" }}
            </strong>
            <ul>
                @foreach($pendentes as $pendente)
                    <li><a href="{{ route('ajuda.show', $pendente->id) }}">{{ $pendente->titulo }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Abas por tipo --}}
    <nav class="store-tabs" aria-label="Tipos de conteúdo">
        <a href="{{ route('ajuda.index', array_filter(['q' => $busca])) }}" class="store-tab {{ $tipo ? '' : 'is-active' }}">
            Tudo <span>{{ $totalGeral }}</span>
        </a>
        @foreach(\App\Models\HelpArticle::TIPOS as $chave => [$singular, $plural, $icone])
            <a href="{{ route('ajuda.index', array_filter(['tipo' => $chave, 'q' => $busca])) }}" class="store-tab {{ $tipo === $chave ? 'is-active' : '' }}">
                <ion-icon name="{{ $icone }}"></ion-icon> {{ $plural }} <span>{{ $contagem[$chave] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('ajuda.index') }}" class="filter-bar" role="search">
        @if($tipo)
            <input type="hidden" name="tipo" value="{{ $tipo }}">
        @endif

        <input type="search" name="q" value="{{ $busca }}" placeholder="O que você procura? Ex.: impressora, férias, nota fiscal" aria-label="Buscar na Central de Ajuda">

        @if($categorias->isNotEmpty())
            <select name="categoria" aria-label="Categoria">
                <option value="">Todas as categorias</option>
                @foreach($categorias as $nome)
                    <option value="{{ $nome }}" {{ $categoria === $nome ? 'selected' : '' }}>{{ $nome }}</option>
                @endforeach
            </select>
        @endif

        <button type="submit" class="btn btn-primary">Buscar</button>

        @if($filtrando)
            <a href="{{ route('ajuda.index', array_filter(['tipo' => $tipo])) }}" class="btn btn-outline-secondary">Limpar</a>
        @endif
    </form>

    @if($artigos->total() > 0)
        <p class="list-count">{{ $artigos->total() }} {{ $artigos->total() === 1 ? 'resultado' : 'resultados' }}</p>

        <div class="help-list">
            @foreach($artigos as $artigo)
                @php
                    $lida = in_array($artigo->id, $lidas, true);
                    $pedeCiencia = $artigo->tipo === 'circular' && $artigo->exige_ciencia;
                @endphp

                @if($artigo->tipo === 'faq')
                    {{-- Pergunta frequente: abre a resposta ali mesmo, sem trocar de página --}}
                    <details class="help-item help-faq">
                        <summary>
                            <ion-icon name="{{ $artigo->icone() }}" class="help-icon" aria-hidden="true"></ion-icon>
                            <span class="help-item-title">{{ $artigo->titulo }}</span>
                            @include('ajuda._chips')
                        </summary>
                        <div class="help-content">
                            {!! $artigo->conteudoHtml() !!}
                            <p><a href="{{ route('ajuda.show', $artigo) }}">Abrir em página própria{{ $artigo->anexos_count ? ' (com arquivos)' : '' }}</a></p>
                        </div>
                    </details>
                @else
                    <a href="{{ route('ajuda.show', $artigo) }}" class="help-item">
                        <ion-icon name="{{ $artigo->icone() }}" class="help-icon" aria-hidden="true"></ion-icon>
                        <span class="help-item-body">
                            <span class="help-item-title">{{ $artigo->titulo }}</span>
                            @if($artigo->resumo)
                                <span class="help-item-summary">{{ $artigo->resumo }}</span>
                            @endif
                            <span class="help-item-meta">
                                {{ $artigo->tipoNome() }}
                                @if($artigo->publicado_em) · {{ $artigo->publicado_em->format('d/m/Y') }} @endif
                                @if($artigo->anexos_count) · {{ $artigo->anexos_count }} {{ $artigo->anexos_count === 1 ? 'arquivo' : 'arquivos' }} @endif
                            </span>
                        </span>
                        @include('ajuda._chips')
                    </a>
                @endif
            @endforeach
        </div>

        <div class="pager">
            {{ $artigos->links('pagination::bootstrap-5') }}
        </div>
    @else
        <div class="tasks-empty">
            @if($filtrando)
                Nada encontrado para essa busca. Tente outras palavras ou abra uma tarefa para o TI.
            @else
                Ainda não há conteúdo publicado aqui{{ $podeGerenciar ? '. Use "Novo conteúdo" para começar.' : '.' }}
            @endif
        </div>
    @endif
</div>
@endsection
