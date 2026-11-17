@props([
    'tamanho' => 40,     // altura do ícone em pixels
    'comTexto' => true,  // mostra "AlpDesk Solutions" ao lado do ícone
])
@php
    // Mesmo ícone da favicon (public/img/icon-192.png). Para trocar, use APP_LOGO no .env.
    $padrao = 'img/icon-192.png';
    $arquivo = config('alpdesk.logo', $padrao);

    // Se o arquivo configurado não existe, usa o padrão; se nenhum existir, mostra só o nome
    if (! is_file(public_path($arquivo))) {
        $arquivo = is_file(public_path($padrao)) ? $padrao : null;
    }

    $tamanho = (int) $tamanho;
    $fonte = round($tamanho * 0.5);
@endphp
{{-- Estilos inline: funcionam no menu (styles.css) e no login (Tailwind) sem precisar recompilar nada --}}
<span {{ $attributes->merge(['class' => 'alp-logo']) }} style="display:inline-flex;align-items:center;gap:{{ round($tamanho / 4) }}px;line-height:1;">
    @if($arquivo)
        <img src="{{ asset($arquivo) }}?v={{ @filemtime(public_path($arquivo)) }}"
             alt="{{ $comTexto ? '' : 'AlpDesk Solutions' }}"
             width="{{ $tamanho }}" height="{{ $tamanho }}"
             style="width:{{ $tamanho }}px;height:{{ $tamanho }}px;max-width:none;border-radius:{{ round($tamanho * 0.22) }}px;"
             onerror="this.remove()">
    @endif
    @if($comTexto || ! $arquivo)
        <span style="display:flex;flex-direction:column;gap:2px;font-family:'Roboto',sans-serif;">
            <span class="logo-texto" style="font-size:{{ $fonte }}px;font-weight:900;color:#0f7a68;letter-spacing:-0.5px;">AlpDesk</span>
            <span style="font-size:{{ max(10, round($fonte * 0.48)) }}px;font-weight:500;color:#1eb39a;letter-spacing:{{ round($fonte * 0.12, 1) }}px;text-transform:uppercase;">Solutions</span>
        </span>
    @endif
</span>
