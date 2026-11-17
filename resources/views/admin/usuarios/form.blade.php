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

    <h1 class="admin-title">{{ $usuario ? 'Editar usuário' : 'Novo usuário' }}</h1>

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
</div>
@endsection
