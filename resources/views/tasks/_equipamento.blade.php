@php
    // Um campo só: o usuário digita e escolhe na lista; o ID do equipamento vai num campo escondido
    $itens = $equipamentos->map(fn ($equipamento) => [
        'id' => $equipamento->id,
        'rotulo' => trim(($equipamento->asset_tag ? $equipamento->asset_tag.' · ' : '').$equipamento->name.' ('.$equipamento->type.') — '.($equipamento->store->name ?? 'Sem loja')),
    ]);

    // Se dois equipamentos tiverem o mesmo texto, acrescenta o ID para diferenciar
    $repeticoes = $itens->countBy('rotulo');
    $itens = $itens->map(fn ($item) => $repeticoes[$item['rotulo']] > 1
        ? [...$item, 'rotulo' => $item['rotulo'].' · ID '.$item['id']]
        : $item);

    $mapa = $itens->pluck('id', 'rotulo');
    $idEscolhido = old('equipment_id', $task->equipment_id ?? '');
    $textoEscolhido = $itens->firstWhere('id', (int) $idEscolhido)['rotulo'] ?? '';
@endphp

<div class="field {{ $errors->has('equipment_id') ? 'has-error' : '' }}">
    <label for="equipamento-busca">Equipamento <span class="optional">(opcional)</span></label>
    <input type="text" id="equipamento-busca" list="lista-equipamentos" autocomplete="off"
           value="{{ $textoEscolhido }}" placeholder="Digite o patrimônio ou o nome e escolha na lista">
    <datalist id="lista-equipamentos">
        @foreach($itens as $item)
            <option value="{{ $item['rotulo'] }}"></option>
        @endforeach
    </datalist>
    <input type="hidden" name="equipment_id" id="equipment_id" value="{{ $textoEscolhido !== '' ? $idEscolhido : '' }}">
    <p class="field-hint">Somente equipamentos de informática. Deixe em branco se o problema não é de um equipamento específico.</p>
    @error('equipment_id')
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>

<script>
    (function () {
        var campo = document.getElementById('equipamento-busca');
        var oculto = document.getElementById('equipment_id');
        var mapa = @json($mapa);
        if (!campo || !oculto) return;

        function sincronizar() {
            var texto = campo.value.trim();

            if (texto === '') {
                oculto.value = '';
                campo.setCustomValidity('');
            } else if (Object.prototype.hasOwnProperty.call(mapa, texto)) {
                oculto.value = mapa[texto];
                campo.setCustomValidity('');
            } else {
                oculto.value = '';
                campo.setCustomValidity('Escolha um equipamento da lista ou deixe o campo em branco.');
            }
        }

        campo.addEventListener('input', sincronizar);
        campo.addEventListener('change', sincronizar);
        sincronizar();
    })();
</script>
