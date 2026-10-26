@php
    $selecionado = old('equipment_id', $task->equipment_id ?? '');
    $porLoja = $equipamentos->groupBy(fn ($equipamento) => $equipamento->store->name ?? 'Sem loja');
@endphp

<div class="form-group mb-3">
    <label for="equipment_id">Equipamento (opcional):</label>
    <input type="search" id="equipamento-busca" class="form-control mb-2" autocomplete="off"
           placeholder="Digite o patrimônio, o nome ou a loja para filtrar a lista">
    <select name="equipment_id" id="equipment_id" class="form-select">
        <option value="">Nenhum (problema geral)</option>
        @foreach($porLoja as $loja => $itens)
            <optgroup label="{{ $loja }}">
                @foreach($itens as $equipamento)
                    <option value="{{ $equipamento->id }}" {{ (string) $selecionado === (string) $equipamento->id ? 'selected' : '' }}>
                        {{ $equipamento->asset_tag ? $equipamento->asset_tag.' · ' : '' }}{{ $equipamento->name }} ({{ $equipamento->type }})
                    </option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    <div class="form-text">Somente equipamentos da área de informática.</div>
    @error('equipment_id')
        <div class="text-danger small">{{ $message }}</div>
    @enderror
</div>

<script>
    (function () {
        var lista = document.getElementById('equipment_id');
        var busca = document.getElementById('equipamento-busca');
        if (!lista || !busca) return;

        var original = lista.cloneNode(true);

        busca.addEventListener('input', function () {
            var termo = busca.value.trim().toLowerCase();
            var atual = lista.value;

            lista.innerHTML = '';

            Array.prototype.forEach.call(original.children, function (no) {
                if (no.tagName !== 'OPTGROUP') {
                    lista.appendChild(no.cloneNode(true));
                    return;
                }

                var grupo = no.cloneNode(false);
                var lojaCombina = no.label.toLowerCase().indexOf(termo) > -1;

                Array.prototype.forEach.call(no.children, function (opcao) {
                    var combina = !termo || lojaCombina || opcao.value === atual
                        || opcao.textContent.toLowerCase().indexOf(termo) > -1;
                    if (combina) grupo.appendChild(opcao.cloneNode(true));
                });

                if (grupo.children.length) lista.appendChild(grupo);
            });

            lista.value = atual;
        });
    })();
</script>
