@extends('layouts.main')

@section('title', $artigo->titulo)

@section('content')
@php
    $podeGerenciar = auth()->user()->can('faq.gerenciar');
    $pedeCiencia = $artigo->tipo === 'circular' && $artigo->exige_ciencia;
    $videos = $artigo->anexos->filter->ehVideo();
    $imagens = $artigo->anexos->filter->ehImagem();
    $arquivos = $artigo->anexos->reject(fn ($a) => $a->ehImagem() || $a->ehVideo());
@endphp

<div id="admin-page" class="admin-narrow help-page">
    <a href="{{ route('ajuda.index', ['tipo' => $artigo->tipo]) }}" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar para {{ mb_strtolower(\App\Models\HelpArticle::TIPOS[$artigo->tipo][1]) }}
    </a>

    <article class="help-article">
        <header class="help-article-header">
            <p class="help-article-type">
                <ion-icon name="{{ $artigo->icone() }}" aria-hidden="true"></ion-icon> {{ $artigo->tipoNome() }}
                @if($artigo->categoria) · {{ $artigo->categoria }} @endif
                @if(! $artigo->publicado) <span class="chip chip-muted">Rascunho</span> @endif
            </p>
            <h1>{{ $artigo->titulo }}</h1>
            @if($artigo->resumo)
                <p class="subtitle">{{ $artigo->resumo }}</p>
            @endif
            <p class="help-item-meta">
                @if($artigo->publicado_em) Publicado em {{ $artigo->publicado_em->format('d/m/Y') }} @endif
                @if($artigo->updated_at && $artigo->publicado_em && $artigo->updated_at->gt($artigo->publicado_em->copy()->addMinute()))
                    · atualizado em {{ $artigo->updated_at->format('d/m/Y') }}
                @endif
                @if($podeGerenciar) · Para: {{ $artigo->publicoDescricao() }} @endif
            </p>
        </header>

        {{-- Circular: confirmação de leitura --}}
        @if($pedeCiencia && $artigo->publicado)
            @if($leitura)
                <div class="alert alert-success" role="status">
                    <ion-icon name="checkmark-circle-outline"></ion-icon>
                    Você confirmou a leitura em {{ $leitura->lido_em->format('d/m/Y \à\s H:i') }}.
                </div>
            @else
                <div class="alert alert-warning" role="status">
                    Esta circular pede confirmação de leitura. Leia até o final e clique em <strong>Li e estou ciente</strong>.
                </div>
            @endif
        @endif

        @if(filled($artigo->conteudo))
            <div class="help-content">
                {!! $artigo->conteudoHtml() !!}
            </div>
        @endif

        {{-- Vídeos: tocam na própria página. preload="metadata" só baixa a duração e a primeira imagem;
             o resto do vídeo vem aos pedaços, conforme a pessoa assiste. --}}
        @if($videos->isNotEmpty())
            <div class="help-videos">
                @foreach($videos as $video)
                    <figure>
                        <video controls preload="metadata" playsinline>
                            <source src="{{ route('ajuda.anexo', [$artigo, $video]) }}" type="{{ $video->mime === 'video/quicktime' ? 'video/mp4' : $video->mime }}">
                            Seu navegador não conseguiu tocar este vídeo.
                        </video>
                        <figcaption>
                            {{ $video->nome_original }} · {{ $video->tamanhoLegivel() }} ·
                            <a href="{{ route('ajuda.anexo', [$artigo, $video]) }}?baixar=1">Baixar</a>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif

        {{-- Imagens do tutorial aparecem no texto, em tamanho de leitura --}}
        @if($imagens->isNotEmpty())
            <div class="help-images">
                @foreach($imagens as $imagem)
                    <figure>
                        <a href="{{ route('ajuda.anexo', [$artigo, $imagem]) }}" target="_blank" rel="noopener">
                            <img src="{{ route('ajuda.anexo', [$artigo, $imagem]) }}" alt="{{ $imagem->nome_original }}" loading="lazy">
                        </a>
                        <figcaption>{{ $imagem->nome_original }}</figcaption>
                    </figure>
                @endforeach
            </div>
        @endif

        @if($arquivos->isNotEmpty())
            <section class="help-files" aria-labelledby="titulo-arquivos">
                <h2 id="titulo-arquivos">Arquivos</h2>
                <ul>
                    @foreach($arquivos as $arquivo)
                        <li>
                            <ion-icon name="{{ $arquivo->icone() }}" aria-hidden="true"></ion-icon>
                            <a href="{{ route('ajuda.anexo', [$artigo, $arquivo]) }}" target="_blank" rel="noopener">{{ $arquivo->nome_original }}</a>
                            <small class="muted">{{ $arquivo->tamanhoLegivel() }}</small>
                            @if($arquivo->abreNoNavegador())
                                <a href="{{ route('ajuda.anexo', [$artigo, $arquivo]) }}?baixar=1" class="help-download">Baixar</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if($pedeCiencia && $artigo->publicado && ! $leitura)
            <form method="POST" action="{{ route('ajuda.ciente', $artigo) }}" class="help-ciente">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <ion-icon name="checkmark-done-outline"></ion-icon> Li e estou ciente
                </button>
                <p class="field-hint">Sua confirmação fica registrada com data e hora.</p>
            </form>
        @endif
    </article>

    @if($podeGerenciar)
        <div class="help-admin-actions">
            <a href="{{ route('ajuda.edit', $artigo) }}" class="btn btn-outline-secondary">
                <ion-icon name="create-outline"></ion-icon> Editar
            </a>
            @if($artigo->tipo === 'circular')
                <a href="{{ route('ajuda.leituras', $artigo) }}" class="btn btn-outline-secondary">
                    <ion-icon name="people-outline"></ion-icon> Quem leu
                </a>
            @endif
            <form method="POST" action="{{ route('ajuda.destroy', $artigo) }}"
                  onsubmit="return confirm('Excluir este conteúdo e todos os arquivos dele? Esta ação não pode ser desfeita.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">
                    <ion-icon name="trash-outline"></ion-icon> Excluir
                </button>
            </form>
            <p class="field-hint">
                @if($artigo->autor) Criado por {{ $artigo->autor->name }}. @endif
                @if($artigo->editor && $artigo->editor->isNot($artigo->autor)) Última alteração por {{ $artigo->editor->name }}. @endif
            </p>
        </div>
    @endif
</div>
@endsection
