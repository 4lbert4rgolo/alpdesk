@extends('layouts.main')

@section('title', 'Nova tarefa - AlpDesk')

@section('content')
<div id="admin-page" class="admin-narrow">
    <a href="/tarefas" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar às tarefas
    </a>

    <h1 class="admin-title">Nova tarefa</h1>
    <p class="subtitle">
        Escolha um modelo pronto ou abra uma tarefa livre.
        @can('modelos.gerenciar')
            <a href="{{ route('admin.modelos.index') }}">Editar ou remover modelos</a>
        @endcan
    </p>

    <div class="template-choices">
        @foreach($modelos as $modelo)
            <a href="/tasks/create?modelo={{ $modelo->id }}" class="template-choice">
                <ion-icon name="document-text-outline" aria-hidden="true"></ion-icon>
                <span>
                    <strong>{{ $modelo->name }}</strong>
                    @if($modelo->descricao)
                        <small>{{ $modelo->descricao }}</small>
                    @endif
                    @if($modelo->sector)
                        <span class="sector-tag">{{ $modelo->sector->name }}</span>
                    @endif
                </span>
            </a>
        @endforeach

        <a href="/tasks/create?livre=1" class="template-choice is-free">
            <ion-icon name="create-outline" aria-hidden="true"></ion-icon>
            <span>
                <strong>Tarefa livre</strong>
                <small>Descreva o pedido do seu jeito (ex.: problema no computador, impressora, sistema).</small>
            </span>
        </a>
    </div>
</div>
@endsection
