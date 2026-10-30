@php
    $maximo = max(1, collect($itens)->max('valor'));
@endphp

@if(count($itens) > 0)
    <ul class="bars">
        @foreach($itens as $item)
            <li>
                <span class="bar-label" title="{{ $item['rotulo'] }}">{{ $item['rotulo'] }}</span>
                <span class="bar-track">
                    <span class="bar-fill {{ ($item['apagado'] ?? false) ? 'is-muted' : '' }}" style="width: {{ round($item['valor'] / $maximo * 100) }}%"></span>
                </span>
                <span class="bar-value">{{ $item['valor'] }}</span>
            </li>
        @endforeach
    </ul>
@else
    <p class="muted">{{ $vazio ?? 'Ainda não há dados para mostrar.' }}</p>
@endif
