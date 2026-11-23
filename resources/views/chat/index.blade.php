@extends('layouts.main')

@section('title', $conversa ? $conversa->nomePara($eu).' - Chat' : 'Chat')

@section('content')
<div class="chat {{ $conversa ? 'has-open' : '' }}">

    {{-- ============================== Lista de conversas ============================== --}}
    <aside class="chat-list" aria-label="Conversas">
        <div class="chat-list-header">
            <h1>Chat</h1>
        </div>

        @if($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <details class="chat-new" {{ $conversas->isEmpty() ? 'open' : '' }}>
            <summary class="btn btn-primary btn-sm"><ion-icon name="add-outline"></ion-icon> Nova conversa</summary>
            <form method="POST" action="{{ route('chat.individual') }}" class="chat-new-form">
                @csrf
                <label for="nova-pessoa">Conversar com</label>
                <select id="nova-pessoa" name="user_id" required>
                    <option value="">Escolha uma pessoa…</option>
                    @foreach($pessoas as $pessoa)
                        <option value="{{ $pessoa->id }}">{{ $pessoa->name }}{{ $pessoa->sector ? ' · '.$pessoa->sector->name : '' }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm">Abrir conversa</button>
            </form>

            @if($podeCriarGrupo)
                <form method="POST" action="{{ route('chat.grupos.store') }}" class="chat-new-form">
                    @csrf
                    <label for="novo-grupo">Ou crie um grupo</label>
                    <input type="text" id="novo-grupo" name="nome" maxlength="80" placeholder="Nome do grupo (ex.: Gerentes)" required>
                    <input type="search" class="people-search" placeholder="Buscar pessoa" aria-label="Buscar pessoa" data-busca-lista="membros-novo">
                    <div class="people-list" id="membros-novo">
                        @foreach($pessoas as $pessoa)
                            <label class="check-inline people-item" data-busca="{{ mb_strtolower($pessoa->name.' '.($pessoa->sector->name ?? '')) }}">
                                <input type="checkbox" name="membros[]" value="{{ $pessoa->id }}"> {{ $pessoa->name }}
                                @if($pessoa->sector)
                                    <small class="muted">{{ $pessoa->sector->name }}</small>
                                @endif
                            </label>
                        @endforeach
                    </div>
                    <button type="submit" class="btn btn-outline-primary btn-sm">Criar grupo</button>
                </form>
            @endif
        </details>

        @if($conversas->isNotEmpty())
            <input type="search" class="people-search chat-filter" id="filtro-conversas" placeholder="Buscar conversa" aria-label="Buscar conversa">
            <ul class="chat-conversations" id="lista-conversas">
                @foreach($conversas as $item)
                    @php
                        $nome = $item->nomePara($eu);
                        $ultima = $item->ultimaMensagem;
                        $previa = $ultima ? ($ultima->corpo ? \Illuminate\Support\Str::limit($ultima->corpo, 60) : '📎 '.$ultima->anexo_nome) : 'Nenhuma mensagem ainda';
                    @endphp
                    <li data-busca="{{ mb_strtolower($nome) }}">
                        <a href="{{ route('chat.show', $item) }}" class="{{ $conversa && $conversa->id === $item->id ? 'is-active' : '' }}">
                            <span class="avatar {{ $item->ehGrupo() ? 'is-group' : '' }}" aria-hidden="true">
                                @if($item->ehGrupo())
                                    <ion-icon name="people-outline"></ion-icon>
                                @else
                                    {{ mb_strtoupper(mb_substr($nome, 0, 1)) }}
                                @endif
                            </span>
                            <span class="chat-conv-text">
                                <strong>{{ $nome }}</strong>
                                <small>
                                    @if($ultima && $item->ehGrupo() && $ultima->user)
                                        {{ (int) $ultima->user_id === (int) $eu->id ? 'Você' : \Illuminate\Support\Str::before($ultima->user->name, ' ') }}:
                                    @endif
                                    {{ $previa }}
                                </small>
                            </span>
                            @if($item->nao_lidas > 0)
                                <span class="chat-badge" aria-label="{{ $item->nao_lidas }} não lidas">{{ $item->nao_lidas }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="muted chat-empty-list">Nenhuma conversa ainda. Comece uma em "Nova conversa".</p>
        @endif
    </aside>

    {{-- ============================== Conversa aberta ============================== --}}
    <section class="chat-room" aria-label="Conversa">
        @if($conversa)
            <header class="chat-room-header">
                <a href="{{ route('chat.index') }}" class="chat-back" aria-label="Voltar às conversas"><ion-icon name="arrow-back-outline"></ion-icon></a>
                <div>
                    <h2>{{ $conversa->nomePara($eu) }}</h2>
                    <small class="muted">
                        @if($conversa->ehGrupo())
                            {{ $conversa->participantes->count() }} pessoas: {{ $conversa->participantes->pluck('name')->map(fn ($n) => \Illuminate\Support\Str::before($n, ' '))->join(', ') }}
                        @else
                            @php $outra = $conversa->participantes->firstWhere('id', '!=', $eu->id); @endphp
                            {{ $outra?->sector?->name ?? 'Conversa individual' }}{{ $outra && ! $outra->ativo ? ' · conta desativada' : '' }}
                        @endif
                    </small>
                </div>

                @if($conversa->ehGrupo())
                    <details class="chat-group-menu">
                        <summary class="btn btn-outline-secondary btn-sm"><ion-icon name="settings-outline"></ion-icon> Grupo</summary>
                        <div class="chat-group-panel">
                            @if($podeGerenciar)
                                <form method="POST" action="{{ route('chat.grupos.update', $conversa) }}" class="chat-new-form">
                                    @csrf
                                    @method('PUT')
                                    <label for="nome-grupo">Nome do grupo</label>
                                    <input type="text" id="nome-grupo" name="nome" value="{{ $conversa->nome }}" maxlength="80" required>
                                    <span class="field-hint">Membros</span>
                                    <input type="search" class="people-search" placeholder="Buscar pessoa" aria-label="Buscar pessoa" data-busca-lista="membros-grupo">
                                    <div class="people-list" id="membros-grupo">
                                        @php $membrosAtuais = $conversa->participantes->pluck('id'); @endphp
                                        @foreach($pessoas as $pessoa)
                                            <label class="check-inline people-item" data-busca="{{ mb_strtolower($pessoa->name.' '.($pessoa->sector->name ?? '')) }}">
                                                <input type="checkbox" name="membros[]" value="{{ $pessoa->id }}" {{ $membrosAtuais->contains($pessoa->id) ? 'checked' : '' }}> {{ $pessoa->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Salvar grupo</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('chat.sair', $conversa) }}" onsubmit="return confirm('Sair deste grupo?')">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm">Sair do grupo</button>
                            </form>
                        </div>
                    </details>
                @endif
            </header>

            <div class="chat-messages" id="chat-mensagens" aria-live="polite">
                @forelse($mensagens as $mensagem)
                    @include('chat._mensagem', ['mensagem' => $mensagem])
                @empty
                    <p class="muted chat-empty" id="chat-vazio">Nenhuma mensagem ainda. Diga olá!</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('chat.mensagens.store', $conversa) }}" enctype="multipart/form-data" class="chat-compose" id="chat-form">
                @csrf
                <label for="chat-arquivo" class="chat-attach" title="Anexar imagem ou PDF">
                    <ion-icon name="attach-outline"></ion-icon>
                    <span class="visually-hidden">Anexar arquivo</span>
                </label>
                <input type="file" id="chat-arquivo" name="anexo" accept="image/jpeg,image/png,image/webp,application/pdf" hidden>
                <div class="chat-compose-main">
                    <span class="chat-file-name" id="chat-arquivo-nome" hidden></span>
                    <textarea name="corpo" id="chat-texto" rows="1" maxlength="4000" placeholder="Escreva uma mensagem. Use #123 para citar uma tarefa." aria-label="Mensagem"></textarea>
                </div>
                <button type="submit" class="btn btn-primary" id="chat-enviar">Enviar</button>
            </form>
            <p class="chat-error" id="chat-erro" role="alert" hidden></p>
        @else
            <div class="chat-placeholder">
                <ion-icon name="chatbubbles-outline" aria-hidden="true"></ion-icon>
                <p>Escolha uma conversa ao lado ou comece uma nova.</p>
                <p class="muted">Pedidos continuam sendo tarefas: no chat, escreva #número para citar uma tarefa.</p>
            </div>
        @endif
    </section>
</div>

<script>
    // Busca nas listas de pessoas e de conversas
    document.querySelectorAll('[data-busca-lista]').forEach(function (campo) {
        campo.addEventListener('input', function () {
            var termo = campo.value.trim().toLowerCase();
            document.querySelectorAll('#' + campo.dataset.buscaLista + ' [data-busca]').forEach(function (item) {
                item.hidden = termo !== '' && item.dataset.busca.indexOf(termo) === -1;
            });
        });
    });

    (function () {
        var filtro = document.getElementById('filtro-conversas');
        if (!filtro) return;
        filtro.addEventListener('input', function () {
            var termo = filtro.value.trim().toLowerCase();
            document.querySelectorAll('#lista-conversas [data-busca]').forEach(function (item) {
                item.hidden = termo !== '' && item.dataset.busca.indexOf(termo) === -1;
            });
        });
    })();
</script>

@if($conversa)
<script>
    // Conversa aberta: envia sem recarregar a página e busca mensagens novas a cada 4 segundos.
    (function () {
        var caixa = document.getElementById('chat-mensagens');
        var form = document.getElementById('chat-form');
        var texto = document.getElementById('chat-texto');
        var arquivo = document.getElementById('chat-arquivo');
        var nomeArquivo = document.getElementById('chat-arquivo-nome');
        var erro = document.getElementById('chat-erro');
        var botao = document.getElementById('chat-enviar');
        var urlNovas = @json(route('chat.mensagens.index', $conversa));
        var cabecalhos = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

        // Maior id já mostrado: a próxima busca pede só o que veio depois dele
        function ultimaId() {
            var todas = caixa.querySelectorAll('[data-id]');
            return todas.length ? parseInt(todas[todas.length - 1].dataset.id, 10) : 0;
        }

        function pertoDoFim() {
            return caixa.scrollHeight - caixa.scrollTop - caixa.clientHeight < 120;
        }

        function rolarParaOFim() {
            caixa.scrollTop = caixa.scrollHeight;
        }

        // O HTML vem pronto (e escapado) do servidor, igual ao das mensagens já na tela
        function acrescentar(id, html) {
            if (caixa.querySelector('[data-id="' + id + '"]')) return; // já está na tela
            var vazio = document.getElementById('chat-vazio');
            if (vazio) vazio.remove();
            caixa.insertAdjacentHTML('beforeend', html);
        }

        function buscarNovas() {
            if (document.hidden) return; // aba escondida: não gasta o servidor
            var estavaNoFim = pertoDoFim();
            fetch(urlNovas + '?depois=' + ultimaId(), { headers: cabecalhos })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (dados) {
                    if (!dados || !dados.mensagens.length) return;
                    dados.mensagens.forEach(function (m) { acrescentar(m.id, m.html); });
                    if (estavaNoFim) rolarParaOFim();
                })
                .catch(function () {});
        }

        form.addEventListener('submit', function (evento) {
            evento.preventDefault();
            if (!texto.value.trim() && !arquivo.files.length) return;

            erro.hidden = true;
            botao.disabled = true;

            fetch(form.action, { method: 'POST', headers: cabecalhos, body: new FormData(form) })
                .then(function (r) {
                    return r.json().then(function (dados) { return { ok: r.ok, dados: dados }; });
                })
                .then(function (resposta) {
                    if (!resposta.ok) {
                        var lista = resposta.dados.errors ? Object.values(resposta.dados.errors)[0] : null;
                        erro.textContent = lista ? lista[0] : 'Não foi possível enviar. Tente de novo.';
                        erro.hidden = false;
                        return;
                    }
                    acrescentar(resposta.dados.id, resposta.dados.html);
                    texto.value = '';
                    arquivo.value = '';
                    nomeArquivo.hidden = true;
                    rolarParaOFim();
                })
                .catch(function () {
                    erro.textContent = 'Sem conexão com o servidor. Tente de novo.';
                    erro.hidden = false;
                })
                .finally(function () {
                    botao.disabled = false;
                    texto.focus();
                });
        });

        // Enter envia; Shift+Enter quebra a linha
        texto.addEventListener('keydown', function (evento) {
            if (evento.key === 'Enter' && !evento.shiftKey) {
                evento.preventDefault();
                form.requestSubmit();
            }
        });

        arquivo.addEventListener('change', function () {
            var escolhido = arquivo.files[0];
            nomeArquivo.hidden = !escolhido;
            nomeArquivo.textContent = escolhido ? '📎 ' + escolhido.name : '';
            if (escolhido && escolhido.size > 10 * 1024 * 1024) {
                erro.textContent = 'O arquivo passa de 10 MB.';
                erro.hidden = false;
                arquivo.value = '';
                nomeArquivo.hidden = true;
            }
        });

        rolarParaOFim();
        texto.focus();
        setInterval(buscarNovas, 4000);
    })();
</script>
@endif
@endsection
