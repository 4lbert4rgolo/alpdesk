@php
    $padrao = 'img/alpdesk_logo.png';
    $arquivo = config('alpdesk.logo', $padrao);

    // Se o arquivo configurado não existe, usa o logo padrão; se nenhum existir, mostra o nome em texto
    if (! is_file(public_path($arquivo))) {
        $arquivo = is_file(public_path($padrao)) ? $padrao : null;
    }
@endphp
@if($arquivo)
    <img src="{{ asset($arquivo) }}?v={{ @filemtime(public_path($arquivo)) }}" alt="AlpDesk Solutions" {{ $attributes }}
         onerror="this.onerror=null;this.replaceWith(Object.assign(document.createElement('span'),{className:'logo-texto',textContent:'AlpDesk Solutions'}))">
@else
    <span class="logo-texto">AlpDesk Solutions</span>
@endif
