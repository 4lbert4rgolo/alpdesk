@extends('layouts.main')

@section('title', $modelo ? 'Editar modelo' : 'Novo modelo')

@section('content')
@php
    // Campos para desenhar: o que voltou com erro (old), os do modelo ou uma linha vazia
    $campos = old('campos', $modelo->campos ?? [['chave' => '', 'rotulo' => '', 'tipo' => 'texto', 'obrigatorio' => true, 'opcoes' => []]]);
    $textoOpcoes = fn ($opcoes) => is_array($opcoes) ? implode("\n", $opcoes) : (string) $opcoes;
@endphp

<div id="admin-page" class="admin-narrow">
    <a href="{{ route('admin.modelos.index') }}" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar aos modelos
    </a>

    <h1 class="admin-title">{{ $modelo ? 'Editar modelo' : 'Novo modelo' }}</h1>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Revise o formulário.</strong> {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" class="admin-form" id="form-modelo"
          action="{{ $modelo ? route('admin.modelos.update', $modelo) : route('admin.modelos.store') }}">
        @csrf
        @if($modelo)
            @method('PUT')
        @endif

        <div class="field">
            <label for="name">Nome do modelo</label>
            <input type="text" id="name" name="name" value="{{ old('name', $modelo->name ?? '') }}" maxlength="120" required autofocus
                   placeholder="Ex.: Devolução de valores">
        </div>

        <div class="field">
            <label for="descricao">Descrição curta <span class="optional">(aparece na escolha do modelo)</span></label>
            <input type="text" id="descricao" name="descricao" value="{{ old('descricao', $modelo->descricao ?? '') }}" maxlength="255">
        </div>

        <div class="field">
            <label for="sector_id">Setor que recebe</label>
            <select id="sector_id" name="sector_id">
                <option value="">Quem abre a tarefa escolhe</option>
                @foreach($setores as $setor)
                    <option value="{{ $setor->id }}" {{ (string) old('sector_id', $modelo->sector_id ?? '') === (string) $setor->id ? 'selected' : '' }}>{{ $setor->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="prioridade">Prioridade sugerida</label>
            <select id="prioridade" name="prioridade">
                <option value="">Padrão ({{ config('tarefas.prioridades')[config('tarefas.prioridade_padrao', 2)][0] }})</option>
                @foreach(config('tarefas.prioridades') as $nivel => $info)
                    <option value="{{ $nivel }}" {{ (string) old('prioridade', $modelo->prioridade ?? '') === (string) $nivel ? 'selected' : '' }}>{{ $info[0] }}</option>
                @endforeach
            </select>
            <p class="field-hint">Já vem marcada ao abrir a tarefa; quem abre pode mudar.</p>
        </div>

        @php
            $responsaveisMarcados = collect(old('responsaveis', $modelo->responsaveis ?? []))->map(fn ($id) => (int) $id);
            $participantesMarcados = collect(old('participantes', $modelo->participantes ?? []))->map(fn ($id) => (int) $id);
        @endphp

        <fieldset class="field {{ $errors->has('responsaveis') || $errors->has('responsaveis.*') ? 'has-error' : '' }}">
            <legend>Responsável automático <span class="optional">(opcional)</span></legend>
            <p class="field-hint">Quem já recebe a tarefa quando ela é aberta por este modelo. Aparecem as pessoas do setor escolhido acima que podem atender tarefas.</p>
            <div class="people-list" id="lista-responsaveis">
                @foreach($atendentes as $pessoa)
                    <label class="check-inline people-item" data-setor="{{ $pessoa->sector_id }}">
                        <input type="checkbox" name="responsaveis[]" value="{{ $pessoa->id }}" {{ $responsaveisMarcados->contains($pessoa->id) ? 'checked' : '' }}>
                        {{ $pessoa->name }}
                        <small class="muted">{{ $pessoa->sector->name ?? 'sem setor' }}</small>
                    </label>
                @endforeach
            </div>
            <p class="field-hint" id="sem-responsaveis" hidden>Ninguém deste setor pode atender tarefas ainda. Dê a permissão "Assumir tarefas" a alguém do setor (em Grupos de acesso) e coloque o setor no cadastro da pessoa.</p>
            @error('responsaveis')
                <p class="field-error">{{ $message }}</p>
            @enderror
            @error('responsaveis.*')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </fieldset>

        <fieldset class="field {{ $errors->has('participantes.*') ? 'has-error' : '' }}">
            <legend>Participantes sugeridos <span class="optional">(opcional)</span></legend>
            <p class="field-hint">Já vêm marcados ao abrir a tarefa (ex.: o gerente que autoriza). Quem abre pode tirar ou acrescentar pessoas.</p>
            <input type="search" id="busca-participantes-modelo" class="people-search" placeholder="Buscar pessoa pelo nome ou setor" aria-label="Buscar pessoa">
            <div class="people-list" id="lista-participantes-modelo">
                @foreach($pessoas as $pessoa)
                    <label class="check-inline people-item" data-busca="{{ mb_strtolower($pessoa->name.' '.($pessoa->sector->name ?? '')) }}">
                        <input type="checkbox" name="participantes[]" value="{{ $pessoa->id }}" {{ $participantesMarcados->contains($pessoa->id) ? 'checked' : '' }}>
                        {{ $pessoa->name }}
                        @if($pessoa->sector)
                            <small class="muted">{{ $pessoa->sector->name }}</small>
                        @endif
                    </label>
                @endforeach
            </div>
            @error('participantes.*')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </fieldset>

        <div class="field">
            <label for="instrucoes">Instruções e lembretes <span class="optional">(aparecem em destaque no formulário)</span></label>
            <textarea id="instrucoes" name="instrucoes" rows="4" maxlength="3000"
                      placeholder="Ex.: IMPORTANTE: apresentar a nota fiscal de devolução...">{{ old('instrucoes', $modelo->instrucoes ?? '') }}</textarea>
        </div>

        <fieldset class="field">
            <legend>Campos que a pessoa preenche</legend>
            <p class="field-hint">O título, o prazo, a prioridade, as observações e os anexos já fazem parte de toda tarefa. Aqui entram só os dados específicos do pedido.</p>

            <div id="lista-campos" class="template-fields">
                @foreach($campos as $i => $campo)
                    <div class="template-field" data-campo>
                        <input type="hidden" name="campos[{{ $i }}][chave]" value="{{ $campo['chave'] ?? '' }}">
                        <div class="template-field-main">
                            <input type="text" name="campos[{{ $i }}][rotulo]" value="{{ $campo['rotulo'] ?? '' }}" maxlength="120" required
                                   placeholder="Nome do campo (ex.: Valor da devolução)" aria-label="Nome do campo">
                            <select name="campos[{{ $i }}][tipo]" aria-label="Tipo do campo" data-tipo>
                                @foreach($tipos as $chave => $rotulo)
                                    <option value="{{ $chave }}" {{ ($campo['tipo'] ?? 'texto') === $chave ? 'selected' : '' }}>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            <label class="check-inline">
                                <input type="checkbox" name="campos[{{ $i }}][obrigatorio]" value="1" {{ ! empty($campo['obrigatorio']) ? 'checked' : '' }}> Obrigatório
                            </label>
                        </div>
                        <textarea name="campos[{{ $i }}][opcoes]" rows="3" maxlength="1000" data-opcoes
                                  placeholder="Uma opção por linha (ex.: Reserva, O.S., Requisição)"
                                  {{ ($campo['tipo'] ?? '') === 'opcoes' ? '' : 'hidden' }}>{{ $textoOpcoes($campo['opcoes'] ?? []) }}</textarea>
                        @error("campos.$i.opcoes")
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                        <div class="template-field-actions">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-subir title="Subir">↑</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-descer title="Descer">↓</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" data-remover>Remover</button>
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="button" class="btn btn-outline-primary btn-sm" id="adicionar-campo">
                <ion-icon name="add-outline"></ion-icon> Adicionar campo
            </button>
        </fieldset>

        <label class="check" for="pede_equipamento">
            <input type="checkbox" id="pede_equipamento" name="pede_equipamento" value="1" {{ old('pede_equipamento', $modelo->pede_equipamento ?? false) ? 'checked' : '' }}>
            <span>Pedir o equipamento <small>Mostra a escolha de equipamento de informática (útil em modelos da TI).</small></span>
        </label>

        <label class="check" for="ativo">
            <input type="checkbox" id="ativo" name="ativo" value="1" {{ old('ativo', $modelo->ativo ?? true) ? 'checked' : '' }}>
            <span>Ativo <small>Desmarque para esconder o modelo de quem abre tarefas, sem perder as tarefas já abertas.</small></span>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ $modelo ? 'Salvar modelo' : 'Criar modelo' }}</button>
            <a href="{{ route('admin.modelos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>

    @if($modelo)
        <form method="POST" action="{{ route('admin.modelos.destroy', $modelo) }}" class="danger-zone"
              onsubmit="return confirm('Excluir este modelo? As {{ $modelo->tasks_count }} tarefa(s) abertas com ele continuam como estão.')">
            @csrf
            @method('DELETE')
            <p class="field-hint">Prefere só esconder o modelo? Desmarque "Ativo" acima.</p>
            <button type="submit" class="btn btn-outline-danger btn-sm">Excluir modelo</button>
        </form>
    @endif
</div>

<template id="modelo-campo">
    <div class="template-field" data-campo>
        <input type="hidden" data-nome="chave" value="">
        <div class="template-field-main">
            <input type="text" data-nome="rotulo" maxlength="120" required placeholder="Nome do campo" aria-label="Nome do campo">
            <select data-nome="tipo" aria-label="Tipo do campo" data-tipo>
                @foreach($tipos as $chave => $rotulo)
                    <option value="{{ $chave }}">{{ $rotulo }}</option>
                @endforeach
            </select>
            <label class="check-inline"><input type="checkbox" data-nome="obrigatorio" value="1" checked> Obrigatório</label>
        </div>
        <textarea data-nome="opcoes" rows="3" maxlength="1000" data-opcoes placeholder="Uma opção por linha" hidden></textarea>
        <div class="template-field-actions">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-subir title="Subir">↑</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-descer title="Descer">↓</button>
            <button type="button" class="btn btn-outline-danger btn-sm" data-remover>Remover</button>
        </div>
    </div>
</template>

<script>
    // Editor dos campos: adicionar, remover, mudar a ordem e mostrar as opções quando o tipo é "Lista".
    (function () {
        var lista = document.getElementById('lista-campos');
        var molde = document.getElementById('modelo-campo');
        // Números novos começam depois dos que já existem, para não repetir nomes campos[N]
        var proximo = lista.querySelectorAll('[data-campo]').length + 100;

        document.getElementById('adicionar-campo').addEventListener('click', function () {
            var linha = molde.content.firstElementChild.cloneNode(true);
            var n = proximo++;
            linha.querySelectorAll('[data-nome]').forEach(function (campo) {
                campo.name = 'campos[' + n + '][' + campo.dataset.nome + ']';
            });
            lista.appendChild(linha);
            linha.querySelector('input[type=text]').focus();
        });

        lista.addEventListener('click', function (evento) {
            var linha = evento.target.closest('[data-campo]');
            if (!linha) return;

            if (evento.target.closest('[data-remover]')) {
                if (lista.querySelectorAll('[data-campo]').length > 1) linha.remove();
            } else if (evento.target.closest('[data-subir]') && linha.previousElementSibling) {
                lista.insertBefore(linha, linha.previousElementSibling);
            } else if (evento.target.closest('[data-descer]') && linha.nextElementSibling) {
                lista.insertBefore(linha.nextElementSibling, linha);
            }
        });

        // Responsáveis: mostra só as pessoas do setor escolhido; quem some da lista é desmarcado
        var setor = document.getElementById('sector_id');
        var responsaveis = document.getElementById('lista-responsaveis');
        var avisoVazio = document.getElementById('sem-responsaveis');

        function filtrarResponsaveis() {
            var visiveis = 0;
            responsaveis.querySelectorAll('[data-setor]').forEach(function (item) {
                var mostrar = setor.value === '' || item.dataset.setor === setor.value;
                item.hidden = !mostrar;
                if (!mostrar) item.querySelector('input').checked = false;
                if (mostrar) visiveis++;
            });
            avisoVazio.hidden = visiveis > 0;
        }

        setor.addEventListener('change', filtrarResponsaveis);
        filtrarResponsaveis();

        // Participantes sugeridos: busca por nome ou setor
        var buscaParticipantes = document.getElementById('busca-participantes-modelo');
        buscaParticipantes.addEventListener('input', function () {
            var termo = buscaParticipantes.value.trim().toLowerCase();
            document.querySelectorAll('#lista-participantes-modelo [data-busca]').forEach(function (item) {
                item.hidden = termo !== '' && item.dataset.busca.indexOf(termo) === -1;
            });
        });

        lista.addEventListener('change', function (evento) {
            if (!evento.target.matches('[data-tipo]')) return;
            var opcoes = evento.target.closest('[data-campo]').querySelector('[data-opcoes]');
            opcoes.hidden = evento.target.value !== 'opcoes';
        });
    })();
</script>
@endsection
