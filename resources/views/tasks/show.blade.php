@extends('layouts.main')

@section('title', $task->title)

@section('content')
@php
    $usuario = auth()->user();
    $responsaveis = $task->users->unique('id');
    $participantes = $task->participantes->unique('id');
    $ehResponsavel = $task->ehResponsavel($usuario);
    $ehParticipante = $task->ehParticipante($usuario);
    $aberta = $task->estaAberta();
    $podeGerenciar = $task->podeGerenciar($usuario);
    $podeAtender = $task->podeAtender($usuario);
    $podeAssumir = $task->podeAssumir($usuario);
    $podeGerenciarParticipantes = $task->podeGerenciarParticipantes($usuario);
    $podeAutorizar = $task->podeAutorizar($usuario);
    $temImagem = $task->hasImage();
    $podeAnexar = $task->podeAnexar($usuario);
    $itens = is_array($task->items) ? $task->items : [];
    $palavrasDeAlerta = ['sensíveis', 'inoperante'];

    // Última decisão de autorização de cada participante (para o resumo)
    $decisoes = $task->atividades
        ->whereIn('tipo', ['autorizacao', 'recusa'])
        ->groupBy('user_id')
        ->map(fn ($itens) => $itens->last());
@endphp

<div id="task-page" class="col-md-10 offset-md-1">
    <a href="/tarefas" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar às tarefas
    </a>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="task-badges">
                <span class="badge-priority prio-{{ $task->priority }}">{{ $task->prioridadeLabel() }}</span>
                <span class="badge-status status-{{ $task->status }}">{{ $task->statusLabel() }}</span>
                <span class="muted">#{{ $task->id }}</span>
                @if($task->template || $task->temRespostas())
                    <span class="sector-tag">{{ $task->template->name ?? 'Modelo' }}</span>
                @endif
            </div>

            <h1 class="task-heading">{{ $task->title }}</h1>

            @if(! $aberta)
                <div class="task-closed-box status-{{ $task->status }}">
                    <strong>
                        {{ $task->status === 'concluida' ? 'Concluída' : 'Cancelada' }}
                        @if($task->concluida_em)
                            em {{ $task->concluida_em->format('d/m/Y H:i') }}
                        @endif
                        @if($task->concluidaPor)
                            por {{ $task->concluidaPor->name }}
                        @endif
                    </strong>
                    @if($task->solucao)
                        <p>{{ $task->solucao }}</p>
                    @endif
                </div>
            @endif

            @if($task->temRespostas())
                <section class="task-section">
                    <h2>Dados do pedido</h2>
                    <dl class="task-answers">
                        @foreach($task->respostas as $resposta)
                            <div>
                                <dt>{{ $resposta['rotulo'] }}</dt>
                                <dd class="{{ ($resposta['tipo'] ?? '') === 'texto_longo' ? 'is-long' : '' }}">{{ \App\Models\TaskTemplate::formatar($resposta) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif

            @if(! $task->temRespostas() || trim((string) $task->description) !== '')
                <section class="task-section">
                    <h2>{{ $task->temRespostas() ? 'Observações' : 'Descrição' }}</h2>
                    <p class="task-description">{{ $task->description }}</p>
                </section>
            @endif

            <section class="task-section">
                <h2>Contexto do problema</h2>
                @if(count($itens) > 0)
                    <ul class="context-chips">
                        @foreach($itens as $item)
                            @php
                                $alerta = collect($palavrasDeAlerta)->contains(fn ($palavra) => str_contains(mb_strtolower($item), $palavra));
                            @endphp
                            <li class="{{ $alerta ? 'is-alert' : '' }}">
                                <ion-icon name="{{ $alerta ? 'alert-circle-outline' : 'checkmark-circle-outline' }}"></ion-icon>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="muted">Nenhuma informação adicional foi marcada.</p>
                @endif
            </section>

            {{-- Linha do tempo: comentários, autorizações e tudo o que aconteceu --}}
            <section class="task-section" id="linha-do-tempo">
                <h2>Linha do tempo</h2>

                @if($task->atividades->isNotEmpty())
                    <ol class="timeline">
                        @foreach($task->atividades as $atividade)
                            <li class="timeline-item tipo-{{ $atividade->tipo }} {{ $atividade->ehMensagem() ? 'is-message' : '' }}">
                                <span class="timeline-icon" aria-hidden="true"><ion-icon name="{{ $atividade->icone() }}"></ion-icon></span>
                                <div class="timeline-body">
                                    <p class="timeline-head">
                                        <strong>{{ $atividade->user->name ?? 'Usuário removido' }}</strong>
                                        {{ $atividade->descricao() }}
                                        <time datetime="{{ $atividade->created_at->toIso8601String() }}" title="{{ $atividade->created_at->format('d/m/Y H:i') }}">
                                            · {{ $atividade->created_at->format('d/m/Y H:i') }}
                                        </time>
                                    </p>
                                    @if($atividade->corpo)
                                        <p class="timeline-text">{{ $atividade->corpo }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="muted">Nada registrado ainda.</p>
                @endif

                @if($task->podeComentar($usuario))
                    <form action="{{ route('tasks.comments.store', $task->id) }}" method="POST" class="comment-form">
                        @csrf
                        <label for="corpo" class="visually-hidden">Comentário</label>
                        <textarea id="corpo" name="corpo" rows="3" maxlength="3000" required
                                  placeholder="{{ $podeAutorizar ? 'Escreva um comentário ou registre sua autorização' : 'Escreva um comentário' }}">{{ old('corpo') }}</textarea>
                        @error('corpo')
                            <p class="field-error">{{ $message }}</p>
                        @enderror

                        <div class="comment-actions">
                            @if($podeAutorizar)
                                <div class="comment-types" role="radiogroup" aria-label="Tipo de mensagem">
                                    <label><input type="radio" name="tipo" value="comentario" {{ old('tipo', 'comentario') === 'comentario' ? 'checked' : '' }}> Comentário</label>
                                    <label class="is-ok"><input type="radio" name="tipo" value="autorizacao" {{ old('tipo') === 'autorizacao' ? 'checked' : '' }}> Autorizo</label>
                                    <label class="is-bad"><input type="radio" name="tipo" value="recusa" {{ old('tipo') === 'recusa' ? 'checked' : '' }}> Não autorizo</label>
                                </div>
                            @else
                                <input type="hidden" name="tipo" value="comentario">
                            @endif
                            <button type="submit" class="btn btn-primary">Enviar</button>
                        </div>
                    </form>
                @endif
            </section>
        </div>

        <aside class="col-lg-5">
            <div class="task-summary">
                <dl>
                    <div>
                        <dt>Situação</dt>
                        <dd><span class="badge-status status-{{ $task->status }}">{{ $task->statusLabel() }}</span></dd>
                    </div>
                    <div>
                        <dt>Prazo</dt>
                        <dd>
                            {{ $task->deadline->format('d/m/Y') }}
                            @if($task->estaVencida())
                                <span class="deadline-late">Vencida</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Setor</dt>
                        <dd>{{ $task->sector->name ?? 'Sem setor' }}</dd>
                    </div>
                    <div>
                        <dt>Aberta por</dt>
                        <dd>{{ $task->user->name ?? 'Usuário removido' }}</dd>
                    </div>
                    <div>
                        <dt>Aberta em</dt>
                        <dd>{{ $task->created_at->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt>Equipamento</dt>
                        <dd>
                            @if($task->equipment)
                                @if(! $task->equipment->trashed() && auth()->user()->can('equipamentos.ver'))
                                    <a href="{{ route('equipamentos.show', $task->equipment) }}">{{ $task->equipment->name }}</a>
                                @else
                                    {{ $task->equipment->name }}
                                    @if($task->equipment->trashed())
                                        <span class="chip chip-muted">excluído</span>
                                    @endif
                                @endif
                                <small class="muted d-block">{{ $task->equipment->asset_tag ? 'Patrimônio '.$task->equipment->asset_tag.' · ' : '' }}{{ $task->equipment->store->name ?? 'Sem empresa' }}</small>
                            @else
                                <span class="muted">Nenhum</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Responsáveis</dt>
                        <dd>
                            @if($responsaveis->isNotEmpty())
                                <ul class="people">
                                    @foreach($responsaveis as $pessoa)
                                        <li>
                                            <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($pessoa->name, 0, 1)) }}</span>
                                            <span>{{ $pessoa->name }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <span class="muted">Ninguém assumiu ainda</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Participantes</dt>
                        <dd>
                            @if($participantes->isNotEmpty())
                                <ul class="people">
                                    @foreach($participantes as $pessoa)
                                        @php $decisao = $decisoes[$pessoa->id] ?? null; @endphp
                                        <li>
                                            <span class="avatar is-light" aria-hidden="true">{{ mb_strtoupper(mb_substr($pessoa->name, 0, 1)) }}</span>
                                            <span>
                                                {{ $pessoa->name }}
                                                @if($decisao)
                                                    <span class="chip {{ $decisao->tipo === 'autorizacao' ? 'chip-ok' : 'chip-bad' }}">
                                                        {{ $decisao->tipo === 'autorizacao' ? 'Autorizou' : 'Não autorizou' }}
                                                    </span>
                                                @endif
                                            </span>
                                            @if($podeGerenciarParticipantes || $pessoa->id === $usuario->id)
                                                <form action="{{ route('tasks.participants.destroy', [$task->id, $pessoa->id]) }}" method="POST" class="people-remove"
                                                      onsubmit="return confirm('{{ $pessoa->id === $usuario->id ? 'Deixar de participar desta tarefa?' : 'Remover esta pessoa dos participantes?' }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-link btn-sm" title="{{ $pessoa->id === $usuario->id ? 'Sair' : 'Remover' }}">
                                                        {{ $pessoa->id === $usuario->id ? 'Sair' : 'Remover' }}
                                                    </button>
                                                </form>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <span class="muted">Nenhum</span>
                            @endif

                            @if($podeGerenciarParticipantes && $candidatos->isNotEmpty())
                                <form action="{{ route('tasks.participants.store', $task->id) }}" method="POST" class="participant-form">
                                    @csrf
                                    <label for="participante" class="visually-hidden">Adicionar participante</label>
                                    <select id="participante" name="user_id" required>
                                        <option value="">Adicionar participante…</option>
                                        @foreach($candidatos as $candidato)
                                            <option value="{{ $candidato->id }}">{{ $candidato->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Adicionar</button>
                                </form>
                                <p class="field-hint">Participantes veem a tarefa, comentam e podem registrar "Autorizo" ou "Não autorizo".</p>
                                @error('user_id')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            @elseif($podeGerenciarParticipantes)
                                {{-- Sem ninguém para convidar: todas as contas ativas já estão na tarefa (ou só existe a sua) --}}
                                <p class="field-hint">
                                    Não há outras pessoas ativas para adicionar.
                                    @can('usuarios.gerenciar')
                                        <a href="{{ route('admin.usuarios.create') }}">Cadastre usuários</a> para convidá-los.
                                    @endcan
                                </p>
                            @endif
                        </dd>
                    </div>
                </dl>

                <div class="task-actions">
                    @if($ehResponsavel)
                        <p class="task-note">
                            <ion-icon name="checkmark-circle-outline"></ion-icon> Você é responsável por esta tarefa.
                        </p>
                    @elseif($ehParticipante)
                        <p class="task-note">
                            <ion-icon name="eye-outline"></ion-icon> Você participa desta tarefa.
                        </p>
                    @endif

                    @if($aberta && ! $ehResponsavel && $podeAssumir)
                        <form action="/tasks/join/{{ $task->id }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary" id="task-submit">Assumir tarefa</button>
                        </form>
                    @endif

                    {{-- Mudança de situação --}}
                    @if($aberta && $podeAtender)
                        <details class="status-box" {{ $errors->has('observacao') ? 'open' : '' }}>
                            <summary class="btn btn-success">Concluir tarefa</summary>
                            <form action="{{ route('tasks.status', $task->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="acao" value="concluir">
                                <label for="solucao">O que foi feito?</label>
                                <textarea id="solucao" name="observacao" rows="3" maxlength="5000" required
                                          placeholder="Ex.: troquei o cabo de rede e testei a impressão">{{ old('acao') === 'concluir' ? old('observacao') : '' }}</textarea>
                                @if(old('acao') === 'concluir')
                                    @error('observacao') <p class="field-error">{{ $message }}</p> @enderror
                                @endif
                                <button type="submit" class="btn btn-success btn-sm">Confirmar conclusão</button>
                            </form>
                        </details>

                        @if($task->status === 'aguardando')
                            <form action="{{ route('tasks.status', $task->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="acao" value="retomar">
                                <button type="submit" class="btn btn-outline-secondary">Retomar atendimento</button>
                            </form>
                        @else
                            <details class="status-box">
                                <summary class="btn btn-outline-secondary">Marcar como aguardando</summary>
                                <form action="{{ route('tasks.status', $task->id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="acao" value="aguardar">
                                    <label for="aguardando">Aguardando o quê?</label>
                                    <textarea id="aguardando" name="observacao" rows="2" maxlength="5000" required
                                              placeholder="Ex.: autorização do gerente para trocar a peça">{{ old('acao') === 'aguardar' ? old('observacao') : '' }}</textarea>
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Confirmar</button>
                                </form>
                            </details>
                        @endif
                    @endif

                    @if(! $aberta && $task->podeReabrir($usuario))
                        <form action="{{ route('tasks.status', $task->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="acao" value="reabrir">
                            <button type="submit" class="btn btn-outline-secondary">Reabrir tarefa</button>
                        </form>
                    @endif

                    @if($podeGerenciar)
                        <div class="task-owner-actions">
                            <a href="/tasks/edit/{{ $task->id }}" class="btn btn-outline-secondary">Editar</a>
                            @if($aberta)
                                <details class="status-box">
                                    <summary class="btn btn-outline-warning">Cancelar</summary>
                                    <form action="{{ route('tasks.status', $task->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="acao" value="cancelar">
                                        <label for="motivo">Motivo do cancelamento</label>
                                        <textarea id="motivo" name="observacao" rows="2" maxlength="5000" required>{{ old('acao') === 'cancelar' ? old('observacao') : '' }}</textarea>
                                        <button type="submit" class="btn btn-outline-warning btn-sm">Confirmar cancelamento</button>
                                    </form>
                                </details>
                            @endif
                            <form action="/tasks/{{ $task->id }}" method="POST"
                                  onsubmit="return confirm('Excluir esta tarefa? Essa ação não pode ser desfeita.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger">Excluir</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>

            <section class="task-attachments" id="anexos" aria-labelledby="titulo-anexos">
                <h2 id="titulo-anexos">Anexos <span class="muted">({{ $task->anexos->count() + ($temImagem ? 1 : 0) }})</span></h2>

                @if($temImagem)
                    {{-- Imagem enviada antes dos anexos múltiplos --}}
                    <a href="{{ route('tasks.image', $task->id) }}" target="_blank" rel="noopener" class="task-image">
                        <img src="{{ route('tasks.image', $task->id) }}" alt="Anexo da tarefa: {{ $task->title }}">
                        <span>Abrir em tamanho real</span>
                    </a>
                @endif

                @if($task->anexos->isNotEmpty())
                    <ul class="attachment-list">
                        @foreach($task->anexos as $anexo)
                            @php
                                $url = route('tasks.attachments.show', [$task->id, $anexo->id]);
                            @endphp
                            <li>
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="attachment-link">
                                    @if($anexo->ehImagem())
                                        <img src="{{ $url }}" alt="" loading="lazy">
                                    @else
                                        <ion-icon name="document-outline" aria-hidden="true"></ion-icon>
                                    @endif
                                    <span>
                                        <strong>{{ $anexo->nome_original }}</strong>
                                        <small class="muted">{{ $anexo->tamanhoLegivel() }} · {{ $anexo->user->name ?? 'Usuário removido' }} · {{ $anexo->created_at->format('d/m/Y H:i') }}</small>
                                    </span>
                                </a>
                                @if($anexo->podeApagar($usuario, $task))
                                    <form action="{{ route('tasks.attachments.destroy', [$task->id, $anexo->id]) }}" method="POST"
                                          onsubmit="return confirm('Remover o anexo {{ addslashes($anexo->nome_original) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link btn-sm text-danger" title="Remover anexo">
                                            <ion-icon name="trash-outline"></ion-icon><span class="visually-hidden">Remover</span>
                                        </button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @elseif(! $temImagem)
                    <p class="muted">Nenhum arquivo anexado.</p>
                @endif

                @if($podeAnexar)
                    <form action="{{ route('tasks.attachments.store', $task->id) }}" method="POST" enctype="multipart/form-data" class="attachment-form">
                        @csrf
                        <label for="novos-anexos" class="visually-hidden">Anexar arquivos</label>
                        <input type="file" id="novos-anexos" name="anexos[]" multiple required accept="image/jpeg,image/png,image/webp,application/pdf">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                            <ion-icon name="attach-outline"></ion-icon> Anexar
                        </button>
                        <p class="field-hint">Imagens ou PDF, até 10 MB cada.</p>
                        @error('anexos')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                        @error('anexos.*')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </form>
                @endif
            </section>
        </aside>
    </div>
</div>

@endsection
