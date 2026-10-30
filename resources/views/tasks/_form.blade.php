@php
    $editando = ! is_null($task);

    $opcoesContexto = [
        'Problema afeta todo o setor',
        'Máquina não liga / está inoperante',
        'Autorizo acesso remoto',
        'Contém dados sensíveis / sigilosos',
        'Pode ser retirado do local',
    ];

    // O que a pessoa já tinha preenchido volta quando há erro (old); na edição, parte dos dados da tarefa
    $marcados = collect(old('items', $editando ? ($task->items ?? []) : []));
    $prioridade = (string) old('priority', $editando ? (int) $task->priority : 0);
    $prazo = old('deadline', $editando ? $task->deadline->format('Y-m-d') : now()->addDays(3)->toDateString());
@endphp

<form method="POST" action="{{ $editando ? '/tasks/update/'.$task->id : '/tasks' }}" enctype="multipart/form-data" class="admin-form" id="form-tarefa">
    @csrf
    @if($editando)
        @method('PUT')
    @endif

    @if($errors->any())
        <div class="alert alert-danger mb-0" role="alert">
            <strong>Revise os campos destacados.</strong> O que você já preencheu foi mantido.
        </div>
    @endif

    <div class="field {{ $errors->has('title') ? 'has-error' : '' }}">
        <label for="title">Título da tarefa</label>
        <input type="text" id="title" name="title" value="{{ old('title', $task->title ?? '') }}" maxlength="255" required autofocus
               placeholder="Ex.: Computador do caixa não liga">
        @error('title')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="field {{ $errors->has('description') ? 'has-error' : '' }}">
        <label for="description">Descrição</label>
        <textarea id="description" name="description" rows="5" maxlength="5000" required
                  placeholder="Conte o que aconteceu, quando começou e o que você já tentou">{{ old('description', $task->description ?? '') }}</textarea>
        @error('description')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>

    <fieldset class="field {{ $errors->has('priority') ? 'has-error' : '' }}">
        <legend>Urgência</legend>
        <div class="priority-options">
            <label class="priority-option">
                <input type="radio" name="priority" value="0" {{ $prioridade === '0' ? 'checked' : '' }}>
                <span><strong>Normal</strong><small>Dá para trabalhar enquanto aguarda.</small></span>
            </label>
            <label class="priority-option is-urgent">
                <input type="radio" name="priority" value="1" {{ $prioridade === '1' ? 'checked' : '' }}>
                <span><strong>Alta</strong><small>Parou o trabalho ou a venda.</small></span>
            </label>
        </div>
        @error('priority')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </fieldset>

    <div class="field {{ $errors->has('deadline') ? 'has-error' : '' }}">
        <label for="deadline">Prazo para conclusão</label>
        <input type="date" id="deadline" name="deadline" value="{{ $prazo }}" {{ $editando ? '' : 'min='.now()->toDateString() }} required>
        <p class="field-hint">Já sugerimos 3 dias a partir de hoje. Ajuste se precisar.</p>
        @error('deadline')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>

    <fieldset class="field {{ $errors->has('items') || $errors->has('items.*') ? 'has-error' : '' }}">
        <legend>Contexto do problema <span class="optional">(marque o que se aplica)</span></legend>
        @foreach($opcoesContexto as $indice => $opcao)
            <label class="check" for="item-{{ $indice }}">
                <input type="checkbox" id="item-{{ $indice }}" name="items[]" value="{{ $opcao }}" {{ $marcados->contains($opcao) ? 'checked' : '' }}>
                <span>{{ $opcao }}</span>
            </label>
        @endforeach
        @error('items')
            <p class="field-error">{{ $message }}</p>
        @enderror
        @error('items.*')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </fieldset>

    @include('tasks._equipamento')

    <div class="field {{ $errors->has('image') ? 'has-error' : '' }}">
        <label for="image">Imagem <span class="optional">(opcional)</span></label>

        @if($editando && $task->hasImage())
            <div class="current-image">
                <img src="{{ route('tasks.image', $task->id) }}" alt="Imagem atual da tarefa">
                <span>Imagem atual. Escolha outra abaixo para substituir.</span>
            </div>
        @endif

        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
        <p class="field-hint">JPG, PNG ou WEBP, até 5 MB. Um print do erro ajuda a resolver mais rápido.</p>
        <img id="image-preview" class="image-preview" alt="Prévia da imagem escolhida" hidden>
        <input type="hidden" name="tinha_imagem" id="tinha_imagem" value="">

        @if($errors->any() && old('tinha_imagem'))
            <p class="field-warning">Por segurança, o navegador não guarda o anexo quando há erro. Escolha a imagem de novo.</p>
        @endif
        @error('image')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary" data-loading-text="Enviando…">{{ $editando ? 'Salvar alterações' : 'Criar tarefa' }}</button>
        <a href="{{ $editando ? '/tasks/'.$task->id : '/' }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<script>
    (function () {
        var formulario = document.getElementById('form-tarefa');
        if (!formulario) return;

        var arquivo = document.getElementById('image');
        var previa = document.getElementById('image-preview');
        var marca = document.getElementById('tinha_imagem');
        var limite = 5 * 1024 * 1024;

        arquivo.addEventListener('change', function () {
            var escolhido = arquivo.files && arquivo.files[0];

            arquivo.setCustomValidity('');
            marca.value = '';
            previa.hidden = true;

            if (!escolhido) return;

            if (escolhido.size > limite) {
                arquivo.setCustomValidity('A imagem pode ter no máximo 5 MB.');
                arquivo.reportValidity();
                return;
            }

            marca.value = '1';

            if (escolhido.type.indexOf('image/') === 0) {
                previa.src = URL.createObjectURL(escolhido);
                previa.hidden = false;
            }
        });

        var botao = formulario.querySelector('[data-loading-text]');

        formulario.addEventListener('submit', function () {
            if (!botao) return;
            botao.dataset.original = botao.textContent.trim();
            botao.textContent = botao.getAttribute('data-loading-text');
            botao.disabled = true;
        });

        window.addEventListener('pageshow', function () {
            if (botao && botao.dataset.original) {
                botao.textContent = botao.dataset.original;
                botao.disabled = false;
            }
        });
    })();
</script>
