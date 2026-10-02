@extends('layouts.main')

@section('title', $task->title)

@section('content')
@php
    $usuario = auth()->user();
    $responsaveis = $task->users->unique('id');
    $ehResponsavel = $usuario && $responsaveis->contains('id', $usuario->id);
    $ehDono = $usuario && $usuario->id === (int) $task->user_id;
    $vencida = $task->deadline->lt(today());
    $temImagem = $task->image && file_exists(public_path('img/tasks/'.$task->image));
    $itens = is_array($task->items) ? $task->items : [];
    $palavrasDeAlerta = ['sensíveis', 'inoperante'];
@endphp

<div id="task-page" class="col-md-10 offset-md-1">
    <a href="/" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar às tarefas
    </a>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="task-badges">
                <span class="badge-priority {{ $task->priority ? 'is-urgent' : '' }}">
                    {{ $task->priority ? 'Urgente' : 'Normal' }}
                </span>
            </div>

            <h1 class="task-heading">{{ $task->title }}</h1>

            <section class="task-section">
                <h2>Descrição</h2>
                <p class="task-description">{{ $task->description }}</p>
            </section>

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
        </div>

        <aside class="col-lg-5">
            <div class="task-summary">
                <dl>
                    <div>
                        <dt>Prazo</dt>
                        <dd>
                            {{ $task->deadline->format('d/m/Y') }}
                            @if($vencida)
                                <span class="deadline-late">Vencida</span>
                            @endif
                        </dd>
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
                </dl>

                <div class="task-actions">
                    @auth
                        @if($ehResponsavel)
                            <p class="task-note">
                                <ion-icon name="checkmark-circle-outline"></ion-icon> Você é responsável por esta tarefa.
                            </p>
                        @else
                            <form action="/tasks/join/{{ $task->id }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary" id="task-submit">Assumir tarefa</button>
                            </form>
                        @endif

                        @if($ehDono)
                            <div class="task-owner-actions">
                                <a href="/tasks/edit/{{ $task->id }}" class="btn btn-outline-secondary">Editar</a>
                                <form action="/tasks/{{ $task->id }}" method="POST"
                                      onsubmit="return confirm('Excluir esta tarefa? Essa ação não pode ser desfeita.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger">Excluir</button>
                                </form>
                            </div>
                        @endif
                    @else
                        <a href="/login" class="btn btn-primary" id="task-submit">Entre para assumir esta tarefa</a>
                    @endauth
                </div>
            </div>

            @if($temImagem)
                <a href="/img/tasks/{{ $task->image }}" target="_blank" rel="noopener" class="task-image">
                    <img src="/img/tasks/{{ $task->image }}" alt="Anexo da tarefa: {{ $task->title }}">
                    <span>Abrir em tamanho real</span>
                </a>
            @endif
        </aside>
    </div>
</div>

@endsection
