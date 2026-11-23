@extends('layouts.main')

@section('title', $usuario ? 'Editar usuário' : 'Novo usuário')

@section('content')
@php
    $selecionados = collect(old('groups', $usuario ? $usuario->accessGroups->pluck('id')->all() : []))
        ->map(fn ($id) => (int) $id);
@endphp

<div id="admin-page" class="admin-narrow">
    <a href="{{ route('admin.usuarios.index') }}" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar aos usuários
    </a>

    <h1 class="admin-title">
        {{ $usuario ? 'Editar usuário' : 'Novo usuário' }}
        @if($usuario && ! $usuario->ativo)
            <span class="badge-status status-cancelada">Desativado</span>
        @endif
    </h1>

    @error('conta')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <form method="POST" class="admin-form"
          action="{{ $usuario ? route('admin.usuarios.update', $usuario) : route('admin.usuarios.store') }}">
        @csrf
        @if($usuario)
            @method('PUT')
        @endif

        <div class="field">
            <label for="name">Nome</label>
            <input type="text" id="name" name="name" value="{{ old('name', $usuario->name ?? '') }}" required autofocus>
            @error('name')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="{{ old('email', $usuario->email ?? '') }}" required autocomplete="off">
            @error('email')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password">{{ $usuario ? 'Nova senha' : 'Senha provisória' }}</label>
            <input type="password" id="password" name="password" autocomplete="new-password" {{ $usuario ? '' : 'required' }}>
            <p class="field-hint">
                {{ $usuario ? 'Deixe em branco para manter a senha atual.' : 'Mínimo de 8 caracteres. Informe à pessoa e peça para trocá-la no perfil.' }}
            </p>
            @error('password')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">Confirmar senha</label>
            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="sector_id">Setor</label>
                <select id="sector_id" name="sector_id">
                    <option value="">Sem setor</option>
                    @foreach($setores as $setor)
                        <option value="{{ $setor->id }}" {{ (string) old('sector_id', $usuario->sector_id ?? '') === (string) $setor->id ? 'selected' : '' }}>{{ $setor->name }}</option>
                    @endforeach
                </select>
                @error('sector_id')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="store_id">Loja</label>
                <select id="store_id" name="store_id">
                    <option value="">Sem loja</option>
                    @foreach($lojas as $loja)
                        <option value="{{ $loja->id }}" {{ (string) old('store_id', $usuario->store_id ?? '') === (string) $loja->id ? 'selected' : '' }}>{{ $loja->name }}</option>
                    @endforeach
                </select>
                @error('store_id')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>
        </div>
        @can('cadastros.gerenciar')
            <p class="field-hint">
                Não achou o setor ou a loja? <a href="{{ route('admin.cadastros.index', 'setores') }}" target="_blank">Cadastre em Setores e lojas</a> e recarregue esta página.
            </p>
        @endcan

        <fieldset class="field">
            <legend>Grupos de acesso</legend>
            @foreach($grupos as $grupo)
                <label class="check">
                    <input type="checkbox" name="groups[]" value="{{ $grupo->id }}" {{ $selecionados->contains($grupo->id) ? 'checked' : '' }}>
                    <span>
                        <strong>{{ $grupo->name }}</strong>
                        @if($grupo->description)
                            <small>{{ $grupo->description }}</small>
                        @endif
                    </span>
                </label>
            @endforeach
            @error('groups')
                <p class="field-error">{{ $message }}</p>
            @enderror
            @error('groups.*')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ $usuario ? 'Salvar alterações' : 'Criar usuário' }}</button>
            <a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>

    @if($usuario)
        @php
            $voce = $usuario->id === auth()->id();
        @endphp
        <section class="account-panel" aria-labelledby="titulo-conta">
            <h2 id="titulo-conta">Conta</h2>

            <dl class="account-info">
                <div>
                    <dt>Situação</dt>
                    <dd><span class="badge-status {{ $usuario->ativo ? 'status-concluida' : 'status-cancelada' }}">{{ $usuario->ativo ? 'Ativo' : 'Desativado' }}</span></dd>
                </div>
                <div>
                    <dt>Último acesso</dt>
                    <dd>{{ $usuario->ultimo_acesso_em ? $usuario->ultimo_acesso_em->format('d/m/Y H:i') : 'Nunca entrou' }}</dd>
                </div>
                <div>
                    <dt>Conta criada em</dt>
                    <dd>{{ $usuario->created_at?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt>E-mail confirmado</dt>
                    <dd>{{ $usuario->email_verified_at ? 'Sim, em '.$usuario->email_verified_at->format('d/m/Y') : 'Não' }}</dd>
                </div>
                <div>
                    <dt>Verificação em duas etapas</dt>
                    <dd>{{ $usuario->temDoisFatores() ? 'Ativada' : 'Desativada' }}</dd>
                </div>
                <div>
                    <dt>Tarefas</dt>
                    <dd>{{ $usuario->tasks_count }} abertas por ele · responsável em {{ $usuario->tasks_as_participant_count }}</dd>
                </div>
                <div>
                    <dt>Equipamentos com a pessoa</dt>
                    <dd>{{ $usuario->equipamentos_count }}</dd>
                </div>
            </dl>

            @if($equipamentos->isNotEmpty())
                <ul class="person-equipment">
                    @foreach($equipamentos as $item)
                        <li>
                            @can('equipamentos.ver')
                                <a href="{{ route('equipamentos.show', $item) }}">{{ $item->name }}</a>
                            @else
                                {{ $item->name }}
                            @endcan
                            <small class="muted">
                                {{ $item->asset_tag ? 'Patrimônio '.$item->asset_tag : 'Sem patrimônio' }}{{ $item->store ? ' · '.$item->store->name : '' }}
                            </small>
                        </li>
                    @endforeach
                </ul>
                @if($usuario->equipamentos_count > $equipamentos->count())
                    @can('equipamentos.ver')
                        <a href="{{ route('equipamentos.index', ['usuario' => $usuario->id]) }}" class="field-hint">Ver todos os {{ $usuario->equipamentos_count }} equipamentos</a>
                    @endcan
                @endif
            @endif

            <div class="account-actions">
                <form action="{{ route('admin.usuarios.password-link', $usuario) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <ion-icon name="mail-outline"></ion-icon> Enviar link para redefinir a senha
                    </button>
                </form>

                @unless($usuario->email_verified_at)
                    <form action="{{ route('admin.usuarios.verify-email', $usuario) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                            <ion-icon name="checkmark-done-outline"></ion-icon> Marcar e-mail como confirmado
                        </button>
                    </form>
                @endunless

                @if($usuario->temDoisFatores())
                    <form action="{{ route('admin.usuarios.remove-2fa', $usuario) }}" method="POST"
                          onsubmit="return confirm('Remover a verificação em duas etapas desta conta? A pessoa passa a entrar só com a senha.')">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                            <ion-icon name="phone-portrait-outline"></ion-icon> Remover verificação em duas etapas
                        </button>
                    </form>
                @endif

                @unless($voce)
                    @if($usuario->ativo)
                        <form action="{{ route('admin.usuarios.deactivate', $usuario) }}" method="POST"
                              onsubmit="return confirm('Desativar esta conta? A pessoa não conseguirá mais entrar. O histórico e as tarefas dela continuam no sistema.')">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <ion-icon name="ban-outline"></ion-icon> Desativar conta
                            </button>
                        </form>
                    @else
                        <form action="{{ route('admin.usuarios.activate', $usuario) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">
                                <ion-icon name="checkmark-circle-outline"></ion-icon> Reativar conta
                            </button>
                        </form>
                    @endif
                @endunless
            </div>

            <p class="field-hint">
                Para quem saiu da empresa, prefira <strong>desativar</strong>: a pessoa perde o acesso, mas o histórico de tarefas e equipamentos continua mostrando o nome dela.
                @if($voce)
                    Você não pode desativar a sua própria conta.
                @endif
            </p>
        </section>
    @endif
</div>
@endsection
