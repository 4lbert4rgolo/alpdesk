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
    // Prioridade: a da tarefa (edição), a sugerida pelo modelo ou a padrão
    $prioridadeInicial = $editando ? (int) $task->priority : (($modelo ?? null)?->prioridade ?: config('tarefas.prioridade_padrao', 2));
    $prioridade = (string) old('priority', $prioridadeInicial);
    $prazo = old('deadline', $editando ? $task->deadline->format('Y-m-d') : now()->addDays(3)->toDateString());

    // Modelo de tarefa (ex.: Devolução de valores): campos próprios, sem "contexto do problema"
    $modelo = $modelo ?? null;
    $camposModelo = $camposModelo ?? [];
    $ehDeModelo = $editando ? count($camposModelo) > 0 : (bool) $modelo;
    $pedeEquipamento = ! $ehDeModelo || ($modelo?->pede_equipamento ?? false);
    $setorFixo = ! $editando && $modelo && $modelo->sector_id;
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

    @if(! $editando && $modelo)
        <input type="hidden" name="task_template_id" value="{{ $modelo->id }}">
    @endif

    @if($modelo && $modelo->instrucoes)
        <div class="template-instructions" role="note">
            <ion-icon name="information-circle-outline" aria-hidden="true"></ion-icon>
            <div>{!! nl2br(e($modelo->instrucoes)) !!}</div>
        </div>
    @endif

    @php
        $setorEscolhido = (string) old('sector_id', $setorPadrao ?? '');
    @endphp
    @if($setorFixo)
        <p class="field-hint">
            Esta tarefa vai para o setor <strong>{{ $modelo->sector->name ?? '' }}</strong>, definido pelo modelo.
            @if(isset($responsaveisModelo) && $responsaveisModelo->isNotEmpty())
                Responsável: <strong>{{ $responsaveisModelo->pluck('name')->join(', ', ' e ') }}</strong>.
            @endif
        </p>
    @else
    <div class="field {{ $errors->has('sector_id') ? 'has-error' : '' }}">
        <label for="sector_id">Para qual setor?</label>
        <select id="sector_id" name="sector_id" required>
            @foreach($setoresDestino as $setor)
                <option value="{{ $setor->id }}" {{ $setorEscolhido === (string) $setor->id ? 'selected' : '' }}>{{ $setor->name }}</option>
            @endforeach
        </select>
        <p class="field-hint">
            {{ $editando ? 'Trocar o setor encaminha a tarefa: quem atende o novo setor passa a vê-la e recebe um aviso.' : 'A tarefa vai para a fila desse setor, e quem atende o setor recebe um aviso.' }}
        </p>
        @error('sector_id')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>
    @endif

    <div class="field {{ $errors->has('title') ? 'has-error' : '' }}">
        <label for="title">Título da tarefa</label>
        <input type="text" id="title" name="title" value="{{ old('title', $task->title ?? ($modelo->name ?? '')) }}" maxlength="255" required autofocus
               placeholder="Ex.: Computador do caixa não liga">
        @error('title')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>

    @if($ehDeModelo)
        <fieldset class="field template-answers">
            <legend>Dados do pedido</legend>
            @foreach($camposModelo as $campo)
                @php
                    $chave = $campo['chave'];
                    $idCampo = 'resp-'.$chave;
                    $erro = 'respostas.'.$chave;
                    $valorSalvo = $campo['valor'] ?? '';
                    // Valor em R$ gravado como 1234.56 aparece como 1.234,56
                    if (($campo['tipo'] ?? '') === 'valor' && is_numeric($valorSalvo)) {
                        $valorSalvo = number_format((float) $valorSalvo, 2, ',', '.');
                    }
                    $valor = old('respostas.'.$chave, $valorSalvo);
                    $obrigatorio = ! empty($campo['obrigatorio']);
                @endphp
                <div class="field {{ $errors->has($erro) ? 'has-error' : '' }}">
                    <label for="{{ $idCampo }}">{{ $campo['rotulo'] }} @unless($obrigatorio)<span class="optional">(opcional)</span>@endunless</label>
                    @switch($campo['tipo'])
                        @case('texto_longo')
                            <textarea id="{{ $idCampo }}" name="respostas[{{ $chave }}]" rows="3" maxlength="5000" {{ $obrigatorio ? 'required' : '' }}>{{ $valor }}</textarea>
                            @break
                        @case('data')
                            <input type="date" id="{{ $idCampo }}" name="respostas[{{ $chave }}]" value="{{ $valor }}" {{ $obrigatorio ? 'required' : '' }}>
                            @break
                        @case('valor')
                            <div class="input-money">
                                <span>R$</span>
                                <input type="text" id="{{ $idCampo }}" name="respostas[{{ $chave }}]" value="{{ $valor }}" inputmode="decimal" maxlength="20"
                                       placeholder="0,00" {{ $obrigatorio ? 'required' : '' }}>
                            </div>
                            @break
                        @case('numero')
                            <input type="number" id="{{ $idCampo }}" name="respostas[{{ $chave }}]" value="{{ $valor }}" step="1" {{ $obrigatorio ? 'required' : '' }}>
                            @break
                        @case('opcoes')
                        @case('loja')
                        @case('setor')
                            @php
                                $listaOpcoes = $campo['tipo'] === 'opcoes' ? ($campo['opcoes'] ?? []) : \App\Models\TaskTemplate::listaDoTipo($campo['tipo']);
                            @endphp
                            <select id="{{ $idCampo }}" name="respostas[{{ $chave }}]" {{ $obrigatorio ? 'required' : '' }}>
                                <option value="">Escolha…</option>
                                @foreach($listaOpcoes as $opcao)
                                    <option value="{{ $opcao }}" {{ (string) $valor === (string) $opcao ? 'selected' : '' }}>{{ $opcao }}</option>
                                @endforeach
                            </select>
                            @break
                        @default
                            <input type="text" id="{{ $idCampo }}" name="respostas[{{ $chave }}]" value="{{ $valor }}" maxlength="255" {{ $obrigatorio ? 'required' : '' }}>
                    @endswitch
                    @error($erro)
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </fieldset>
    @endif

    <div class="field {{ $errors->has('description') ? 'has-error' : '' }}">
        <label for="description">{{ $ehDeModelo ? 'Observações' : 'Descrição' }} @if($ehDeModelo)<span class="optional">(opcional)</span>@endif</label>
        <textarea id="description" name="description" rows="{{ $ehDeModelo ? 3 : 5 }}" maxlength="5000" {{ $ehDeModelo ? '' : 'required' }}
                  placeholder="{{ $ehDeModelo ? 'Algo mais que quem vai atender precisa saber' : 'Conte o que aconteceu, quando começou e o que você já tentou' }}">{{ old('description', $task->description ?? '') }}</textarea>
        @error('description')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>

    <fieldset class="field {{ $errors->has('priority') ? 'has-error' : '' }}">
        <legend>Prioridade</legend>
        <div class="priority-options priority-options-4">
            @foreach(config('tarefas.prioridades') as $nivel => $info)
                <label class="priority-option prio-option-{{ $nivel }} {{ $nivel >= 4 ? 'is-urgent' : '' }}">
                    <input type="radio" name="priority" value="{{ $nivel }}" {{ $prioridade === (string) $nivel ? 'checked' : '' }}>
                    <span><strong>{{ $info[0] }}</strong><small>{{ $info[1] }}</small></span>
                </label>
            @endforeach
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

    @unless($ehDeModelo)
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

    @endunless

    @if($pedeEquipamento)
        @include('tasks._equipamento')
    @endif

    <div class="field {{ $errors->has('anexos') || $errors->has('anexos.*') ? 'has-error' : '' }}">
        <label for="anexos">Anexos <span class="optional">(opcional)</span></label>

        @if($editando && ($task->anexos->isNotEmpty() || $task->hasImage()))
            <p class="field-hint">
                Esta tarefa já tem {{ $task->anexos->count() + ($task->hasImage() ? 1 : 0) }} anexo(s). Os arquivos escolhidos aqui são acrescentados; para remover algum, use a página da tarefa.
            </p>
        @endif

        <input type="file" id="anexos" name="anexos[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf">
        <p class="field-hint">Imagens (JPG, PNG, WEBP) ou PDF, até 10 MB cada e 10 arquivos por vez. Um print do erro ajuda a resolver mais rápido.</p>
        <ul id="anexos-lista" class="file-list" hidden></ul>
        <input type="hidden" name="tinha_anexo" id="tinha_anexo" value="">

        @if($errors->any() && old('tinha_anexo'))
            <p class="field-warning">Por segurança, o navegador não guarda os anexos quando há erro. Escolha os arquivos de novo.</p>
        @endif
        @error('anexos')
            <p class="field-error">{{ $message }}</p>
        @enderror
        @error('anexos.*')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>

    @if(! $editando && isset($pessoas) && $pessoas->isEmpty())
        <fieldset class="field">
            <legend>Participantes <span class="optional">(opcional)</span></legend>
            <p class="field-hint">
                Ainda não há outras pessoas ativas no sistema para convidar.
                @can('usuarios.gerenciar')
                    <a href="{{ route('admin.usuarios.create') }}" target="_blank">Cadastre usuários</a> e recarregue esta página.
                @endcan
            </p>
        </fieldset>
    @endif

    @if(! $editando && isset($pessoas) && $pessoas->isNotEmpty())
        @php
            // Voltando de um erro: o que a pessoa marcou. Primeira vez: os participantes sugeridos pelo modelo.
            $escolhidos = collect(session()->hasOldInput() ? old('participantes', []) : ($modelo->participantes ?? []))
                ->map(fn ($id) => (string) $id);
            $idsResponsaveis = isset($responsaveisModelo) ? $responsaveisModelo->pluck('id') : collect();
        @endphp
        <fieldset class="field {{ $errors->has('participantes') || $errors->has('participantes.*') ? 'has-error' : '' }}">
            <legend>Participantes <span class="optional">(opcional)</span></legend>
            <p class="field-hint">Pessoas que vão acompanhar a tarefa: elas veem, comentam e podem registrar "Autorizo". Quem atende continua sendo o responsável. Dá para mudar depois, na página da tarefa.</p>

            <input type="search" id="busca-participantes" class="people-search" placeholder="Buscar pessoa pelo nome ou setor" aria-label="Buscar pessoa">
            <div class="people-list" id="lista-participantes">
                @foreach($pessoas->whereNotIn('id', $idsResponsaveis) as $pessoa)
                    <label class="check-inline people-item" data-busca="{{ mb_strtolower($pessoa->name.' '.($pessoa->sector->name ?? '')) }}">
                        <input type="checkbox" name="participantes[]" value="{{ $pessoa->id }}" {{ $escolhidos->contains((string) $pessoa->id) ? 'checked' : '' }}>
                        {{ $pessoa->name }}
                        @if($pessoa->sector)
                            <small class="muted">{{ $pessoa->sector->name }}</small>
                        @endif
                    </label>
                @endforeach
            </div>
            <p class="field-hint" id="contagem-participantes" aria-live="polite"></p>
            @error('participantes')
                <p class="field-error">{{ $message }}</p>
            @enderror
            @error('participantes.*')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </fieldset>

        <script>
            // Filtra a lista de pessoas enquanto digita e mostra quantas foram escolhidas
            (function () {
                var busca = document.getElementById('busca-participantes');
                var lista = document.getElementById('lista-participantes');
                var contagem = document.getElementById('contagem-participantes');

                function contar() {
                    var n = lista.querySelectorAll('input:checked').length;
                    contagem.textContent = n === 0 ? '' : (n === 1 ? '1 participante escolhido.' : n + ' participantes escolhidos.');
                }

                busca.addEventListener('input', function () {
                    var termo = busca.value.trim().toLowerCase();
                    lista.querySelectorAll('[data-busca]').forEach(function (item) {
                        item.hidden = termo !== '' && item.dataset.busca.indexOf(termo) === -1;
                    });
                });
                lista.addEventListener('change', contar);
                contar();
            })();
        </script>
    @endif

    @if(! $editando)
        @can('tarefas.assumir')
            <div class="field">
                <label class="check" for="assumir_agora">
                    <input type="checkbox" id="assumir_agora" name="assumir_agora" value="1" {{ old('assumir_agora') ? 'checked' : '' }}>
                    <span>Eu mesmo vou atender esta tarefa
                        <small>Você entra como responsável e a tarefa já começa "Em andamento".</small></span>
                </label>
            </div>
        @endcan
    @endif

    <div class="form-actions">
        <button type="submit" class="btn btn-primary" data-loading-text="Enviando…">{{ $editando ? 'Salvar alterações' : 'Criar tarefa' }}</button>
        <a href="{{ $editando ? '/tasks/'.$task->id : '/tarefas' }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<script>
    (function () {
        var formulario = document.getElementById('form-tarefa');
        if (!formulario) return;

        // Anexos: mostra os arquivos escolhidos e avisa antes de enviar se algum passar do limite
        var arquivos = document.getElementById('anexos');
        var lista = document.getElementById('anexos-lista');
        var marca = document.getElementById('tinha_anexo');
        var limite = 10 * 1024 * 1024;
        var maximo = 10;

        arquivos.addEventListener('change', function () {
            var escolhidos = Array.prototype.slice.call(arquivos.files || []);

            arquivos.setCustomValidity('');
            lista.innerHTML = '';
            lista.hidden = escolhidos.length === 0;
            marca.value = escolhidos.length ? '1' : '';

            if (escolhidos.length > maximo) {
                arquivos.setCustomValidity('Escolha no máximo ' + maximo + ' arquivos por vez.');
                arquivos.reportValidity();
                return;
            }

            escolhidos.forEach(function (arquivo) {
                var item = document.createElement('li');
                var tamanho = arquivo.size >= 1024 * 1024
                    ? (arquivo.size / 1024 / 1024).toFixed(1).replace('.', ',') + ' MB'
                    : Math.max(1, Math.round(arquivo.size / 1024)) + ' KB';
                item.textContent = arquivo.name + ' (' + tamanho + ')';

                if (arquivo.size > limite) {
                    item.className = 'is-error';
                    arquivos.setCustomValidity('O arquivo "' + arquivo.name + '" passa de 10 MB.');
                }

                lista.appendChild(item);
            });

            arquivos.reportValidity();
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
