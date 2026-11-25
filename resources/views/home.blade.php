@extends('layouts.main')

@section('title', 'Início')

@section('content')
@php
    $primeiroNome = \Illuminate\Support\Str::of($usuario->name)->before(' ');
    $podeGerenciarAjuda = $usuario->can('faq.gerenciar');
@endphp

<div id="admin-page" class="home-page">
    <div class="admin-header">
        <div>
            <h1>{{ $saudacao }}, {{ $primeiroNome }}!</h1>
            <p class="subtitle">{{ ucfirst(now()->locale('pt_BR')->translatedFormat('l, d \d\e F')) }}</p>
        </div>
    </div>

    {{-- Atalhos --}}
    <nav class="home-shortcuts" aria-label="Atalhos">
        @can('tarefas.criar')
            <a href="/tasks/create" class="home-shortcut is-primary">
                <ion-icon name="add-circle-outline" aria-hidden="true"></ion-icon> Abrir chamado
            </a>
        @endcan
        <a href="{{ route('tasks.index') }}" class="home-shortcut">
            <ion-icon name="list-outline" aria-hidden="true"></ion-icon> Tarefas
        </a>
        <a href="{{ route('ajuda.index') }}" class="home-shortcut">
            <ion-icon name="help-buoy-outline" aria-hidden="true"></ion-icon> Central de Ajuda
        </a>
        <a href="{{ route('chat.index') }}" class="home-shortcut">
            <ion-icon name="chatbubbles-outline" aria-hidden="true"></ion-icon> Chat
        </a>
        @can('equipamentos.ver')
            <a href="{{ route('equipamentos.index') }}" class="home-shortcut">
                <ion-icon name="desktop-outline" aria-hidden="true"></ion-icon> Equipamentos
            </a>
        @endcan
    </nav>

    {{-- Circulares que a pessoa ainda precisa confirmar --}}
    @if($circularesPendentes->isNotEmpty())
        <div class="alert alert-warning help-pending" role="status">
            <strong><ion-icon name="megaphone-outline"></ion-icon>
                {{ $circularesPendentes->count() === 1 ? 'Você tem 1 circular para confirmar a leitura:' : "Você tem {$circularesPendentes->count()} circulares para confirmar a leitura:" }}
            </strong>
            <ul>
                @foreach($circularesPendentes as $circular)
                    <li><a href="{{ route('ajuda.show', $circular->id) }}">{{ $circular->titulo }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="home-grid">
        {{-- Comunicados --}}
        <section class="home-card home-news" aria-labelledby="titulo-comunicados">
            <div class="home-card-header">
                <h2 id="titulo-comunicados"><ion-icon name="newspaper-outline" aria-hidden="true"></ion-icon> Comunicados</h2>
                <a href="{{ route('ajuda.index', ['tipo' => 'comunicado']) }}">Ver todos</a>
            </div>

            @forelse($comunicados as $comunicado)
                <article class="home-news-item">
                    <h3>
                        <a href="{{ route('ajuda.show', $comunicado) }}">{{ $comunicado->titulo }}</a>
                        @if($comunicado->fixado)
                            <span class="chip"><ion-icon name="pin-outline"></ion-icon> Fixado</span>
                        @endif
                    </h3>
                    <p class="home-news-text">
                        {{-- Resumo, ou o começo do texto sem a formatação --}}
                        {{ $comunicado->resumo ?: \Illuminate\Support\Str::limit(trim(strip_tags($comunicado->conteudoHtml())), 220) }}
                    </p>
                    <p class="help-item-meta">
                        {{ $comunicado->publicado_em?->format('d/m/Y') }}
                        @if($comunicado->anexos_count) · {{ $comunicado->anexos_count }} {{ $comunicado->anexos_count === 1 ? 'arquivo' : 'arquivos' }} @endif
                    </p>
                </article>
            @empty
                <p class="home-empty">
                    Nenhum comunicado no momento.
                    @if($podeGerenciarAjuda)
                        <a href="{{ route('ajuda.create', ['tipo' => 'comunicado']) }}">Publicar um comunicado</a>
                    @endif
                </p>
            @endforelse
        </section>

        <div class="home-side">
            {{-- Resumo das minhas tarefas --}}
            <section class="home-card" aria-labelledby="titulo-tarefas">
                <div class="home-card-header">
                    <h2 id="titulo-tarefas"><ion-icon name="clipboard-outline" aria-hidden="true"></ion-icon> Minhas tarefas</h2>
                    <a href="{{ route('dashboard') }}">Ver todas</a>
                </div>

                <div class="home-stats">
                    <a href="{{ route('dashboard') }}" class="home-stat">
                        <strong>{{ $tarefas['total'] }}</strong> em aberto
                    </a>
                    <a href="{{ route('dashboard') }}" class="home-stat">
                        <strong>{{ $tarefas['responsavel'] }}</strong> comigo
                    </a>
                    <a href="{{ route('dashboard') }}" class="home-stat {{ $tarefas['aguardando'] ? 'is-warn' : '' }}">
                        <strong>{{ $tarefas['aguardando'] }}</strong> aguardando
                    </a>
                    <a href="{{ route('dashboard') }}" class="home-stat {{ $tarefas['atrasadas'] ? 'is-danger' : '' }}">
                        <strong>{{ $tarefas['atrasadas'] }}</strong> {{ $tarefas['atrasadas'] === 1 ? 'atrasada' : 'atrasadas' }}
                    </a>
                </div>

                @if($tarefas['recentes']->isNotEmpty())
                    <p class="home-sub">Atualizadas por último</p>
                    <ul class="home-tasks">
                        @foreach($tarefas['recentes'] as $tarefa)
                            <li>
                                <a href="/tasks/{{ $tarefa->id }}">#{{ $tarefa->id }} {{ $tarefa->title }}</a>
                                <span class="badge-status status-{{ $tarefa->status }}">{{ $tarefa->statusLabel() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="home-empty">Nenhuma tarefa em aberto com você.</p>
                @endif
            </section>

            {{-- Tutoriais e documentos recentes --}}
            <section class="home-card" aria-labelledby="titulo-tutoriais">
                <div class="home-card-header">
                    <h2 id="titulo-tutoriais"><ion-icon name="school-outline" aria-hidden="true"></ion-icon> Tutoriais recentes</h2>
                    <a href="{{ route('ajuda.index', ['tipo' => 'tutorial']) }}">Ver todos</a>
                </div>

                @forelse($tutoriais as $tutorial)
                    <a href="{{ route('ajuda.show', $tutorial) }}" class="home-link-item">
                        <ion-icon name="{{ $tutorial->icone() }}" aria-hidden="true"></ion-icon>
                        <span>
                            {{ $tutorial->titulo }}
                            <small>{{ $tutorial->tipoNome() }} · {{ $tutorial->publicado_em?->format('d/m/Y') }}</small>
                        </span>
                    </a>
                @empty
                    <p class="home-empty">Ainda não há tutoriais publicados para o seu setor.</p>
                @endforelse
            </section>
        </div>
    </div>
</div>
@endsection
