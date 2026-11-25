{{-- Etiquetas de um conteúdo na lista. Usa $artigo, $lida, $pedeCiencia e $podeGerenciar. --}}
<span class="help-chips">
    @if($artigo->fixado)
        <span class="chip"><ion-icon name="pin-outline"></ion-icon> Fixado</span>
    @endif
    @if(! $artigo->publicado)
        <span class="chip chip-muted">Rascunho</span>
    @endif
    @if($artigo->categoria)
        <span class="chip chip-muted">{{ $artigo->categoria }}</span>
    @endif
    @if($pedeCiencia && $artigo->publicado)
        @if($lida)
            <span class="chip chip-ok"><ion-icon name="checkmark-outline"></ion-icon> Lida</span>
        @else
            <span class="chip chip-warn">Leitura pendente</span>
        @endif
    @endif
    @if($podeGerenciar && ($artigo->setores->isNotEmpty() || $artigo->lojas->isNotEmpty()))
        <span class="chip chip-muted" title="Para quem aparece">{{ $artigo->publicoDescricao() }}</span>
    @endif
</span>
