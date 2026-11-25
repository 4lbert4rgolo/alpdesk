@extends('layouts.main')

@section('title', $artigo ? 'Editar conteúdo' : 'Novo conteúdo')

@section('content')
@php
    $tipoAtual = old('tipo', $artigo->tipo ?? $tipo);
    $setoresMarcados = collect(old('setores', $artigo ? $artigo->setores->modelKeys() : []))->map(fn ($id) => (int) $id);
    $lojasMarcadas = collect(old('lojas', $artigo ? $artigo->lojas->modelKeys() : []))->map(fn ($id) => (int) $id);
    // Conteúdo novo já vem marcado como publicado e, se for circular, pedindo ciência
    $marcado = fn (string $campo, bool $padrao) => (bool) old($campo, $artigo ? $artigo->{$campo} : $padrao);
@endphp

<div id="admin-page" class="admin-narrow help-page">
    <a href="{{ $artigo ? route('ajuda.show', $artigo) : route('ajuda.index') }}" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar
    </a>

    <h1 class="admin-title">{{ $artigo ? 'Editar conteúdo' : 'Novo conteúdo' }}</h1>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Revise o formulário.</strong> {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data" class="admin-form" id="form-ajuda"
          action="{{ $artigo ? route('ajuda.update', $artigo) : route('ajuda.store') }}">
        @csrf
        @if($artigo)
            @method('PUT')
        @endif

        <fieldset class="field">
            <legend>Tipo</legend>
            <div class="help-type-options">
                @foreach(\App\Models\HelpArticle::TIPOS as $chave => [$singular, $plural, $icone])
                    <label class="check-inline help-type-option">
                        <input type="radio" name="tipo" value="{{ $chave }}" {{ $tipoAtual === $chave ? 'checked' : '' }} required>
                        <ion-icon name="{{ $icone }}" aria-hidden="true"></ion-icon> {{ $singular }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="field">
            <label for="titulo">Título</label>
            <input type="text" id="titulo" name="titulo" value="{{ old('titulo', $artigo->titulo ?? '') }}" maxlength="255" required autofocus
                   placeholder="Ex.: Como trocar o papel da impressora fiscal">
        </div>

        <div class="field">
            <label for="resumo">Resumo <span class="optional">(uma frase, aparece na lista)</span></label>
            <input type="text" id="resumo" name="resumo" value="{{ old('resumo', $artigo->resumo ?? '') }}" maxlength="300">
        </div>

        <div class="field">
            <label for="categoria">Categoria <span class="optional">(opcional, ajuda a filtrar)</span></label>
            <input type="text" id="categoria" name="categoria" value="{{ old('categoria', $artigo->categoria ?? '') }}" maxlength="80"
                   list="lista-categorias" placeholder="Ex.: Sistema de vendas, Impressoras, RH">
            <datalist id="lista-categorias">
                @foreach($categorias as $nome)
                    <option value="{{ $nome }}"></option>
                @endforeach
            </datalist>
        </div>

        <div class="field">
            <label for="conteudo">Texto</label>
            <textarea id="conteudo" name="conteudo" rows="14" maxlength="100000"
                      placeholder="Escreva aqui a resposta, o passo a passo ou o comunicado.">{{ old('conteudo', $artigo->conteudo ?? '') }}</textarea>
            <p class="field-hint">
                Dicas de formatação: <code>**negrito**</code>, linha começando com <code>- </code> vira lista,
                <code>1. </code> vira lista numerada, <code>## Título</code> vira um subtítulo e endereços com <code>https://</code> viram links.
            </p>
        </div>

        <fieldset class="field">
            <legend>Para quem aparece</legend>
            <p class="field-hint">Nenhum setor marcado = <strong>todos os setores</strong>. Nenhuma empresa marcada = <strong>todas as empresas</strong>.</p>

            <p class="help-audience-label">Setores</p>
            <div class="people-list help-audience">
                @foreach($setores as $setor)
                    <label class="check-inline people-item">
                        <input type="checkbox" name="setores[]" value="{{ $setor->id }}" {{ $setoresMarcados->contains($setor->id) ? 'checked' : '' }}>
                        {{ $setor->name }}
                    </label>
                @endforeach
            </div>

            <p class="help-audience-label">Empresas</p>
            <div class="people-list help-audience">
                @foreach($lojas as $loja)
                    <label class="check-inline people-item">
                        <input type="checkbox" name="lojas[]" value="{{ $loja->id }}" {{ $lojasMarcadas->contains($loja->id) ? 'checked' : '' }}>
                        {{ $loja->name }}
                    </label>
                @endforeach
            </div>
            <p class="field-hint">Quem ainda não tem setor ou empresa no cadastro só vê o que é para todos.</p>
        </fieldset>

        <div class="field">
            <label for="anexos">Arquivos <span class="optional">(PDF, Word, Excel, PowerPoint e imagens até 20 MB; vídeos até 200 MB)</span></label>
            <input type="file" id="anexos" name="anexos[]" multiple
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp,.mp4,.m4v,.webm,.mov">
            <p class="field-hint">Imagens e vídeos aparecem dentro do texto (bons para tutoriais). Os outros arquivos aparecem para baixar.
                Prefira vídeos em <strong>MP4</strong>: vídeos .mov gravados no iPhone podem não tocar em todos os navegadores. Vídeos grandes demoram para enviar, aguarde a página recarregar.</p>

            @if($artigo && $artigo->anexos->isNotEmpty())
                <ul class="help-files help-files-edit">
                    @foreach($artigo->anexos as $anexo)
                        <li>
                            <ion-icon name="{{ $anexo->icone() }}" aria-hidden="true"></ion-icon>
                            <a href="{{ route('ajuda.anexo', [$artigo, $anexo]) }}" target="_blank" rel="noopener">{{ $anexo->nome_original }}</a>
                            <small class="muted">{{ $anexo->tamanhoLegivel() }}</small>
                            {{-- form="..." liga o botão a um formulário de fora: formulário dentro de formulário não funciona no HTML --}}
                            <button type="submit" form="apagar-anexo-{{ $anexo->id }}" class="btn btn-outline-danger btn-sm">Remover</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <fieldset class="field">
            <legend>Opções</legend>
            <label class="check-inline">
                <input type="checkbox" name="publicado" value="1" {{ $marcado('publicado', true) ? 'checked' : '' }}>
                Publicado <span class="optional">(desmarcado = rascunho, só quem gerencia vê)</span>
            </label>
            <label class="check-inline">
                <input type="checkbox" name="fixado" value="1" {{ $marcado('fixado', false) ? 'checked' : '' }}>
                Fixar no topo da lista
            </label>
            <label class="check-inline" data-so-circular>
                <input type="checkbox" name="exige_ciencia" value="1" {{ $marcado('exige_ciencia', true) ? 'checked' : '' }}>
                Pedir confirmação de leitura ("Li e estou ciente")
            </label>
            @if($artigo && $artigo->tipo === 'circular' && $artigo->leituras()->exists())
                <label class="check-inline" data-so-circular>
                    <input type="checkbox" name="pedir_ciencia_novamente" value="1">
                    Mudei algo importante: apagar as confirmações e pedir que todos leiam de novo
                </label>
            @endif
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ $artigo ? 'Salvar alterações' : 'Salvar' }}</button>
            <a href="{{ $artigo ? route('ajuda.show', $artigo) : route('ajuda.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>

    {{-- Formulários de remover arquivo (fora do formulário principal) --}}
    @if($artigo)
        @foreach($artigo->anexos as $anexo)
            <form method="POST" action="{{ route('ajuda.anexos.destroy', [$artigo, $anexo]) }}" id="apagar-anexo-{{ $anexo->id }}"
                  onsubmit="return confirm('Remover o arquivo {{ e(addslashes($anexo->nome_original)) }}?')" hidden>
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    @endif
</div>

<script>
    // As opções de confirmação de leitura só aparecem quando o tipo é "Circular"
    (function () {
        var form = document.getElementById('form-ajuda');
        function atualizar() {
            var tipo = form.querySelector('input[name="tipo"]:checked');
            var ehCircular = tipo && tipo.value === 'circular';
            form.querySelectorAll('[data-so-circular]').forEach(function (el) { el.hidden = !ehCircular; });
        }
        form.querySelectorAll('input[name="tipo"]').forEach(function (r) { r.addEventListener('change', atualizar); });
        atualizar();
    })();
</script>
@endsection
