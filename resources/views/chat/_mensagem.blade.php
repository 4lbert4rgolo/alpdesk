@php
    $minha = (int) $mensagem->user_id === (int) $eu->id;
@endphp
<div class="chat-msg {{ $minha ? 'is-mine' : '' }}" data-id="{{ $mensagem->id }}">
    @if(! $minha && $conversa->ehGrupo())
        <span class="chat-msg-author">{{ $mensagem->user->name ?? 'Usuário removido' }}</span>
    @endif

    @if($mensagem->corpo)
        <div class="chat-msg-text">{{ $mensagem->corpoFormatado() }}</div>
    @endif

    @if($mensagem->temAnexo())
        @php
            $urlAnexo = route('chat.anexo', [$conversa->id, $mensagem->id]);
        @endphp
        <a href="{{ $urlAnexo }}" target="_blank" rel="noopener" class="chat-msg-file">
            @if($mensagem->anexoEhImagem())
                <img src="{{ $urlAnexo }}" alt="{{ $mensagem->anexo_nome }}" loading="lazy">
            @else
                <ion-icon name="document-outline" aria-hidden="true"></ion-icon> {{ $mensagem->anexo_nome }}
            @endif
        </a>
    @endif

    <time class="chat-msg-time" datetime="{{ $mensagem->created_at->toIso8601String() }}">
        {{ $mensagem->created_at->isToday() ? $mensagem->created_at->format('H:i') : $mensagem->created_at->format('d/m H:i') }}
    </time>
</div>
