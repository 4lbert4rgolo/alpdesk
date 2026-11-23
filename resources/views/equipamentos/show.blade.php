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

            <section class="task-section" id="historico">
                <h2>Histórico e comentários</h2>

                <form action="{{ route('equipamentos.comments.store', $equipamento) }}" method="POST" class="comment-form">
                    @csrf
                    <label for="comentario" class="visually-hidden">Comentário</label>
                    <textarea id="comentario" name="comentario" rows="2" maxlength="2000" required
                              placeholder="Escreva um comentário (ex.: trocado o HD, emprestado para a filial até sexta)">{{ old('comentario') }}</textarea>
                    @error('comentario')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                    <div class="comment-actions">
                        <button type="submit" class="btn btn-primary">Comentar</button>
                    </div>
                </form>

                @if($movimentacoes->isNotEmpty())
                    <ol class="timeline">
                        @foreach($movimentacoes as $movimento)
                            <li class="timeline-item tipo-equip-{{ $movimento->tipo }} {{ $movimento->ehComentario() ? 'is-message' : '' }}">
                                <span class="timeline-icon" aria-hidden="true"><ion-icon name="{{ $movimento->icone() }}"></ion-icon></span>
                                <div class="timeline-body">
                                    <p class="timeline-head">
                                        <strong>{{ $movimento->user->name ?? 'Sistema' }}</strong>
                                        @if(! $movimento->ehComentario())
                                            {{ mb_strtolower($movimento->descricao) }}
                                        @endif
                                        <time datetime="{{ $movimento->created_at->toIso8601String() }}">· {{ $movimento->created_at->format('d/m/Y H:i') }}</time>
                                        @if($movimento->podeApagar(auth()->user()))
                                            <form action="{{ route('equipamentos.comments.destroy', $movimento) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Apagar este comentário?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-link btn-sm timeline-delete">Apagar</button>
                                            </form>
                                        @endif
                                    </p>
                                    @if($movimento->alteracoes)
                                        <ul class="change-list">
                                            @foreach($movimento->alteracoes as $campo => $valores)
                                                <li><strong>{{ $campo }}:</strong> {{ $valores[0] }} <ion-icon name="arrow-forward-outline" aria-label="para"></ion-icon> {{ $valores[1] }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                    @if($movimento->comentario)
                                        <p class="timeline-text">
                                            @if($movimento->tipo === 'alteracao' || $movimento->tipo === 'exclusao')
                                                <strong>Motivo:</strong>
                                            @endif
                                            {{ $movimento->comentario }}
                                        </p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="muted">Nada registrado ainda.</p>
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
                        <dd>
                            @if($equipamento->user)
                                @can('usuarios.gerenciar')
                                    <a href="{{ route('admin.usuarios.edit', $equipamento->user) }}">{{ $equipamento->user->name }}</a>
                                @else
                                    {{ $equipamento->user->name }}
                                @endcan
                                @unless($equipamento->user->ativo)
                                    <span class="badge-status status-cancelada">Conta desativada</span>
                                @endunless
                            @else
                                {{ $equipamento->user_name ?: '—' }}
                                @if($equipamento->user_name)
                                    <small class="muted">(sem conta no sistema)</small>
                                @endif
                            @endif
                        </dd>
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

                <div class="task-actions">
                    @can('equipamentos.gerenciar')
                        <a href="{{ route('equipamentos.edit', $equipamento) }}" class="btn btn-outline-secondary">Editar</a>
                    @endcan
                    <a href="{{ route('equipamentos.label', $equipamento) }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                        <ion-icon name="qr-code-outline"></ion-icon> Imprimir etiqueta
                    </a>
                    @can('equipamentos.excluir')
                        <details class="status-box" {{ $errors->has('motivo') ? 'open' : '' }}>
                            <summary class="btn btn-outline-danger">Excluir</summary>
                            <form action="{{ route('equipamentos.destroy', $equipamento) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <label for="motivo">Motivo da exclusão</label>
                                <textarea id="motivo" name="motivo" rows="2" maxlength="1000" required
                                          placeholder="Ex.: cadastrado em duplicidade">{{ old('motivo') }}</textarea>
                                @error('motivo')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                                <p class="field-hint">O equipamento vai para a lixeira e pode ser restaurado. Se ele foi descartado, prefira mudar a situação para "Baixado".</p>
                                <button type="submit" class="btn btn-outline-danger btn-sm">Enviar para a lixeira</button>
                            </form>
                        </details>
                    @endcan
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
