@extends('layouts.main')

@section('title', $equipamento->name)

@section('content')
<div id="admin-page">
    <a href="{{ route('equipamentos.index', array_filter(['loja' => $equipamento->store_id])) }}" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar aos equipamentos
    </a>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="task-badges">
                <span class="badge-priority">{{ $equipamento->type }}</span>
                <span class="chip">{{ $equipamento->statusLabel() }}</span>
            </div>

            <h1 class="task-heading">{{ $equipamento->name }}</h1>

            <section class="task-section">
                <h2>Especificações</h2>
                @if($equipamento->specs)
                    <p class="task-description">{{ $equipamento->specs }}</p>
                @else
                    <p class="muted">Nenhuma especificação registrada.</p>
                @endif
            </section>

            <section class="task-section">
                <h2>
                    Tarefas deste equipamento
                    @if($equipamento->tasks_count > 0)
                        <span class="chip chip-muted">{{ $equipamento->tasks_count }}</span>
                    @endif
                </h2>
                @if($tarefas->isNotEmpty())
                    <ul class="related-tasks">
                        @foreach($tarefas as $tarefa)
                            <li>
                                <a href="/tasks/{{ $tarefa->id }}">{{ $tarefa->title }}</a>
                                <span class="muted">{{ $tarefa->created_at->format('d/m/Y') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="muted">Nenhuma tarefa foi aberta para este equipamento.</p>
                @endif
            </section>

            @if($equipamento->notes)
                <section class="task-section">
                    <h2>Observações</h2>
                    <p class="note-alert">
                        <ion-icon name="alert-circle-outline"></ion-icon>
                        <span>{{ $equipamento->notes }}</span>
                    </p>
                </section>
            @endif
        </div>

        <aside class="col-lg-5">
            <div class="task-summary">
                <dl>
                    <div>
                        <dt>Loja</dt>
                        <dd>{{ $equipamento->store->name ?? 'Sem loja definida' }}</dd>
                    </div>
                    <div>
                        <dt>Setor</dt>
                        <dd>{{ $equipamento->sector->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Usuário</dt>
                        <dd>{{ $equipamento->user_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Patrimônio</dt>
                        <dd>{{ $equipamento->asset_tag ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Nº de série</dt>
                        <dd>{{ $equipamento->serial_number ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Nome na rede</dt>
                        <dd>{{ $equipamento->hostname ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>AnyDesk</dt>
                        <dd>{{ $equipamento->anydesk_id ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Cadastrado em</dt>
                        <dd>{{ $equipamento->created_at->format('d/m/Y') }}</dd>
                    </div>
                </dl>

                @can('equipamentos.gerenciar')
                    <div class="task-actions">
                        <a href="{{ route('equipamentos.edit', $equipamento) }}" class="btn btn-outline-secondary">Editar</a>
                    </div>
                @endcan
            </div>
        </aside>
    </div>
</div>
@endsection
