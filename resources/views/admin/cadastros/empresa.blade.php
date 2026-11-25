@extends('layouts.main')

@section('title', 'Dados da empresa')

@section('content')
@php
    // old(): se a validação falhar, o formulário volta com o que a pessoa digitou
    $v = fn (string $campo) => old($campo, $empresa->{$campo});
@endphp

<div id="admin-page" class="admin-narrow">
    <a href="{{ route('admin.cadastros.index', 'empresas') }}" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar para empresas
    </a>

    <h1 class="admin-title">{{ $empresa->ehLocal() ? 'Dados do local' : 'Dados da empresa' }}</h1>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Revise o formulário.</strong> {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.cadastros.update', ['empresas', $empresa->id]) }}" class="admin-form">
        @csrf
        @method('PUT')

        @php $tipoAtual = old('tipo', $empresa->tipo ?? 'empresa'); @endphp

        <fieldset class="field">
            <legend>Este cadastro é</legend>
            <label class="check">
                <input type="radio" name="tipo" value="empresa" {{ $tipoAtual === 'empresa' ? 'checked' : '' }}>
                <span>Uma empresa <small>Tem razão social e CNPJ próprios. Aparece nos Termos de Uso e na Política de Privacidade.</small></span>
            </label>
            <label class="check">
                <input type="radio" name="tipo" value="local" {{ $tipoAtual === 'local' ? 'checked' : '' }}>
                <span>Um local, sem CNPJ próprio <small>Ex.: Escritório, depósito. Serve para equipamentos e pessoas; no termo vale a empresa responsável.</small></span>
            </label>
        </fieldset>

        <section class="form-group-block" aria-labelledby="titulo-identificacao">
            <h2 id="titulo-identificacao">Identificação</h2>

            <div class="field">
                <label for="name">Nome fantasia</label>
                <input type="text" id="name" name="name" value="{{ $v('name') }}" maxlength="100" required>
                <p class="field-hint">Nome usado no dia a dia, nas telas e nos filtros.</p>
            </div>

            {{-- Local: escolhe a empresa responsável pelos dados de quem trabalha nele --}}
            <div class="field" data-so-local>
                <label for="empresa_responsavel_id">Empresa responsável</label>
                <select id="empresa_responsavel_id" name="empresa_responsavel_id">
                    <option value="">Nenhuma (o termo usa os dados padrão do .env)</option>
                    @foreach($empresas as $opcao)
                        <option value="{{ $opcao->id }}" {{ (string) old('empresa_responsavel_id', $empresa->empresa_responsavel_id) === (string) $opcao->id ? 'selected' : '' }}>
                            {{ $opcao->name }}{{ $opcao->razao_social ? ' — '.$opcao->razao_social : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="field-hint">Quem trabalha neste local verá nos Termos de Uso e na Política de Privacidade a razão social, o CNPJ e o Encarregado da empresa escolhida.</p>
            </div>

            <div class="field" data-so-empresa>
                <label for="razao_social">Razão social</label>
                <input type="text" id="razao_social" name="razao_social" value="{{ $v('razao_social') }}" maxlength="255">
            </div>

            <div class="field field-curto" data-so-empresa>
                <label for="cnpj">CNPJ</label>
                <input type="text" id="cnpj" name="cnpj" value="{{ $v('cnpj') }}" maxlength="18" inputmode="numeric" placeholder="00.000.000/0000-00">
                <p class="field-hint">Pode digitar só os números: o sistema confere e formata.</p>
            </div>

            <div class="field" data-so-empresa>
                <label for="endereco">Endereço completo</label>
                <input type="text" id="endereco" name="endereco" value="{{ $v('endereco') }}" maxlength="255"
                       placeholder="Rua, número, bairro, cidade/UF, CEP">
            </div>
        </section>

        <section class="form-group-block" aria-labelledby="titulo-lgpd" data-so-empresa>
            <h2 id="titulo-lgpd">Encarregado de dados (LGPD)</h2>
            <p class="field-hint">Pessoa que responde pelas dúvidas e pedidos sobre dados pessoais. Aparece nos Termos de Uso e na Política de Privacidade de quem trabalha nesta empresa.</p>

            <div class="field">
                <label for="encarregado_nome">Nome</label>
                <input type="text" id="encarregado_nome" name="encarregado_nome" value="{{ $v('encarregado_nome') }}" maxlength="255">
            </div>

            <div class="field">
                <label for="encarregado_email">E-mail</label>
                <input type="email" id="encarregado_email" name="encarregado_email" value="{{ $v('encarregado_email') }}" maxlength="255"
                       placeholder="privacidade@empresa.com.br">
            </div>
        </section>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="{{ route('admin.cadastros.index', 'empresas') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>
<script>
    // Mostra só os campos do tipo escolhido: empresa (dados completos) ou local (empresa responsável)
    (function () {
        var form = document.querySelector('form.admin-form');
        function atualizar() {
            var marcado = form.querySelector('input[name="tipo"]:checked');
            var local = marcado && marcado.value === 'local';
            form.querySelectorAll('[data-so-local]').forEach(function (el) { el.hidden = !local; });
            form.querySelectorAll('[data-so-empresa]').forEach(function (el) { el.hidden = local; });
        }
        form.querySelectorAll('input[name="tipo"]').forEach(function (r) { r.addEventListener('change', atualizar); });
        atualizar();
    })();
</script>
@endsection
