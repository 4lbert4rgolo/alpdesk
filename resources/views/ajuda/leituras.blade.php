@extends('layouts.main')

@section('title', 'Quem leu: '.$artigo->titulo)

@section('content')
@php
    $pendentes = $total - $lidas;
    $percentual = $total > 0 ? (int) round($lidas / $total * 100) : 0;
@endphp

<div id="admin-page" class="help-page">
    <a href="{{ route('ajuda.show', $artigo) }}" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar para a circular
    </a>

    <div class="admin-header">
        <div>
            <h1>Quem leu</h1>
            <p class="subtitle">{{ $artigo->titulo }} · Para: {{ $artigo->publicoDescricao() }}</p>
        </div>
    </div>

    @unless($artigo->exige_ciencia)
        <div class="alert alert-info" role="status">
            Esta circular não pede confirmação de leitura. Para acompanhar quem leu, edite e marque
            "Pedir confirmação de leitura".
        </div>
    @endunless

    <div class="help-progress" aria-label="{{ $lidas }} de {{ $total }} confirmaram">
        <div class="help-progress-numbers">
            <strong>{{ $lidas }}</strong> de {{ $total }} confirmaram ({{ $percentual }}%)
            @if($pendentes > 0) · <strong>{{ $pendentes }}</strong> {{ $pendentes === 1 ? 'pendente' : 'pendentes' }} @endif
        </div>
        <div class="help-progress-bar"><span style="width: {{ $percentual }}%"></span></div>
    </div>

    <nav class="store-tabs" aria-label="Filtro">
        <a href="{{ route('ajuda.leituras', $artigo) }}" class="store-tab {{ $filtro ? '' : 'is-active' }}">Todos <span>{{ $total }}</span></a>
        <a href="{{ route('ajuda.leituras', [$artigo, 'filtro' => 'pendentes']) }}" class="store-tab {{ $filtro === 'pendentes' ? 'is-active' : '' }}">Pendentes <span>{{ $pendentes }}</span></a>
        <a href="{{ route('ajuda.leituras', [$artigo, 'filtro' => 'lidas']) }}" class="store-tab {{ $filtro === 'lidas' ? 'is-active' : '' }}">Confirmaram <span>{{ $lidas }}</span></a>
    </nav>

    @if($lista->isNotEmpty())
        <div class="task-table-wrap">
            <table class="task-table">
                <thead>
                    <tr>
                        <th scope="col">Pessoa</th>
                        <th scope="col">Setor</th>
                        <th scope="col">Loja</th>
                        <th scope="col">Leitura</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lista as $linha)
                        <tr>
                            <td data-label="Pessoa">{{ $linha->pessoa->name }}</td>
                            <td data-label="Setor">{{ $linha->pessoa->sector->name ?? '—' }}</td>
                            <td data-label="Loja">{{ $linha->pessoa->store->name ?? '—' }}</td>
                            <td data-label="Leitura">
                                @if($linha->leitura)
                                    <span class="chip chip-ok"><ion-icon name="checkmark-outline"></ion-icon> {{ $linha->leitura->lido_em->format('d/m/Y H:i') }}</span>
                                @else
                                    <span class="chip chip-warn">Pendente</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="tasks-empty">Ninguém nesta lista.</div>
    @endif

    @if($foraDoPublico->isNotEmpty())
        <p class="field-hint" style="margin-top: 1rem">
            Também confirmaram (hoje fora do público desta circular, por exemplo porque mudaram de setor):
            {{ $foraDoPublico->map(fn ($l) => ($l->user->name ?? 'conta removida').' em '.$l->lido_em->format('d/m/Y'))->implode('; ') }}.
        </p>
    @endif
</div>
@endsection
