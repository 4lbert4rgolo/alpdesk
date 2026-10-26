@extends('layouts.main')

@section('title', $equipamento ? 'Editar equipamento' : 'Novo equipamento')

@section('content')
@php
    $v = fn ($campo, $padrao = '') => old($campo, $equipamento->{$campo} ?? $padrao);
@endphp

<div id="admin-page" class="admin-narrow">
    <a href="{{ $equipamento ? route('equipamentos.show', $equipamento) : route('equipamentos.index') }}" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar
    </a>

    <h1 class="admin-title">{{ $equipamento ? 'Editar equipamento' : 'Novo equipamento' }}</h1>

    <form method="POST" class="admin-form"
          action="{{ $equipamento ? route('equipamentos.update', $equipamento) : route('equipamentos.store') }}">
        @csrf
        @if($equipamento)
            @method('PUT')
        @endif

        <div class="field">
            <label for="name">Nome do equipamento</label>
            <input type="text" id="name" name="name" value="{{ $v('name') }}" required autofocus maxlength="255">
            @error('name')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="type">Tipo</label>
                <select id="type" name="type" required>
                    @foreach($tipos as $tipo)
                        <option value="{{ $tipo }}" {{ $v('type', 'Desktop') === $tipo ? 'selected' : '' }}>{{ $tipo }}</option>
                    @endforeach
                </select>
                @error('type')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="status">Situação</label>
                <select id="status" name="status" required>
                    @foreach($situacoes as $chave => $rotulo)
                        <option value="{{ $chave }}" {{ $v('status', 'em_uso') === $chave ? 'selected' : '' }}>{{ $rotulo }}</option>
                    @endforeach
                </select>
                @error('status')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="store_id">Loja</label>
                <select id="store_id" name="store_id">
                    <option value="">Sem loja definida</option>
                    @foreach($lojas as $loja)
                        <option value="{{ $loja->id }}" {{ (string) $v('store_id') === (string) $loja->id ? 'selected' : '' }}>{{ $loja->name }}</option>
                    @endforeach
                </select>
                @error('store_id')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="sector_id">Setor</label>
                <select id="sector_id" name="sector_id">
                    <option value="">Sem setor</option>
                    @foreach($setores as $setor)
                        <option value="{{ $setor->id }}" {{ (string) $v('sector_id') === (string) $setor->id ? 'selected' : '' }}>{{ $setor->name }}</option>
                    @endforeach
                </select>
                @error('sector_id')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="asset_tag">Patrimônio</label>
                <input type="text" id="asset_tag" name="asset_tag" value="{{ $v('asset_tag') }}" maxlength="50">
                @error('asset_tag')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>
            <div class="field">
                <label for="user_name">Usuário (quem usa)</label>
                <input type="text" id="user_name" name="user_name" value="{{ $v('user_name') }}" maxlength="255">
                @error('user_name')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="serial_number">Número de série</label>
                <input type="text" id="serial_number" name="serial_number" value="{{ $v('serial_number') }}" maxlength="100">
                @error('serial_number')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>
            <div class="field">
                <label for="hostname">Nome na rede</label>
                <input type="text" id="hostname" name="hostname" value="{{ $v('hostname') }}" maxlength="100">
                @error('hostname')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="field">
            <label for="anydesk_id">ID do AnyDesk</label>
            <input type="text" id="anydesk_id" name="anydesk_id" value="{{ $v('anydesk_id') }}" maxlength="20">
            @error('anydesk_id')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="specs">Especificações</label>
            <textarea id="specs" name="specs" rows="6" maxlength="5000">{{ $v('specs') }}</textarea>
            @error('specs')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="notes">Observações</label>
            <textarea id="notes" name="notes" rows="3" maxlength="2000">{{ $v('notes') }}</textarea>
            @error('notes')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ $equipamento ? 'Salvar alterações' : 'Cadastrar equipamento' }}</button>
            <a href="{{ $equipamento ? route('equipamentos.show', $equipamento) : route('equipamentos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection
