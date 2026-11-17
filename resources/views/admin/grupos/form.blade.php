@extends('layouts.main')

@section('title', $grupo ? 'Editar grupo' : 'Novo grupo')

@section('content')
@php
    $marcadas = collect(old('permissions', $grupo->permissions ?? []));
    $somenteDescricao = $grupo && $grupo->isAdministrator();
@endphp

<div id="admin-page" class="admin-narrow">
    <a href="{{ route('admin.grupos.index') }}" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar aos grupos
    </a>

    <h1 class="admin-title">{{ $grupo ? 'Editar grupo' : 'Novo grupo' }}</h1>

    <form method="POST" class="admin-form"
          action="{{ $grupo ? route('admin.grupos.update', $grupo) : route('admin.grupos.store') }}">
        @csrf
        @if($grupo)
            @method('PUT')
        @endif

        <div class="field">
            <label for="name">Nome do grupo</label>
            <input type="text" id="name" name="name" value="{{ old('name', $grupo->name ?? '') }}"
                   {{ $somenteDescricao ? 'readonly' : 'required autofocus' }}>
            @error('name')
                <p class="field-error">{{ $message }}</p>
            @enderror
            @error('slug')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="description">Descrição</label>
            <input type="text" id="description" name="description" value="{{ old('description', $grupo->description ?? '') }}" maxlength="255">
            @error('description')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        @if($somenteDescricao)
            <p class="task-note">
                <ion-icon name="checkmark-circle-outline"></ion-icon>
                O grupo Administrador sempre tem todas as permissões, inclusive as que forem criadas no futuro.
            </p>
        @else
            @foreach(\App\Support\Permissoes::secoes() as $secao => $itens)
                <fieldset class="field">
                    <legend>{{ $secao }}</legend>
                    @foreach($itens as $chave => $rotulo)
                        <label class="check">
                            <input type="checkbox" name="permissions[]" value="{{ $chave }}" {{ $marcadas->contains($chave) ? 'checked' : '' }}>
                            <span>{{ $rotulo }}</span>
                        </label>
                    @endforeach
                </fieldset>
            @endforeach
            @error('permissions')
                <p class="field-error">{{ $message }}</p>
            @enderror
            @error('permissions.*')
                <p class="field-error">{{ $message }}</p>
            @enderror
        @endif

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ $grupo ? 'Salvar alterações' : 'Criar grupo' }}</button>
            <a href="{{ route('admin.grupos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>

    @if($grupo && ! $grupo->is_system)
        <form method="POST" action="{{ route('admin.grupos.destroy', $grupo) }}" class="admin-danger"
              onsubmit="return confirm('Excluir este grupo? As pessoas que pertencem a ele perderão as permissões que ele concedia.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">Excluir grupo</button>
        </form>
    @endif
</div>
@endsection
