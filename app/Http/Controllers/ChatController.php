<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Chat interno: conversas individuais e grupos.
 *
 * A tela pergunta ao servidor a cada poucos segundos se chegou mensagem nova
 * (rota "mensagens" com ?depois=<última id>). Não precisa de programa extra no servidor.
 * Só quem está na conversa vê as mensagens: para os outros, a conversa "não existe" (404).
 */
class ChatController extends Controller
{
    /** Quantas mensagens antigas aparecem ao abrir uma conversa */
    private const HISTORICO = 80;

    public function index(Request $request)
    {
        return $this->tela($request, null);
    }

    public function show(Request $request, Conversation $conversa)
    {
        $this->exigirParticipante($conversa, $request->user());

        return $this->tela($request, $conversa);
    }

    /** Abre (ou cria) a conversa individual com a pessoa escolhida */
    public function individual(Request $request)
    {
        $eu = $request->user();

        $dados = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('ativo', true), Rule::notIn([$eu->id])],
        ], [
            'user_id.required' => 'Escolha uma pessoa.',
            'user_id.exists' => 'Escolha uma pessoa ativa da lista.',
            'user_id.not_in' => 'Escolha outra pessoa.',
        ]);

        $conversa = Conversation::individualEntre($eu, User::findOrFail($dados['user_id']));

        return redirect()->route('chat.show', $conversa);
    }

    /** Cria um grupo (quem cria vira gerente do grupo) */
    public function criarGrupo(Request $request)
    {
        $eu = $request->user();
        abort_unless($eu->can('chat.grupos'), 403);

        $dados = $this->validarGrupo($request);

        $conversa = Conversation::create(['tipo' => 'grupo', 'nome' => $dados['nome'], 'created_by' => $eu->id]);
        $conversa->participantes()->attach($eu->id, ['gerente' => true]);
        $conversa->participantes()->attach(collect($dados['membros'])->reject(fn ($id) => (int) $id === $eu->id)->unique()->all());

        return redirect()->route('chat.show', $conversa)->with('msg', 'Grupo "'.$conversa->nome.'" criado.');
    }

    /** Renomeia o grupo e define os membros (gerentes do grupo ou quem tem "chat.grupos") */
    public function atualizarGrupo(Request $request, Conversation $conversa)
    {
        $eu = $request->user();
        $this->exigirParticipante($conversa, $eu);
        abort_unless($conversa->podeGerenciar($eu), 403);

        $dados = $this->validarGrupo($request);

        $conversa->update(['nome' => $dados['nome']]);

        // Mantém os gerentes atuais e quem está editando; o resto vira a lista escolhida
        $gerentes = $conversa->participantes()->wherePivot('gerente', true)->pluck('users.id');
        $membros = collect($dados['membros'])->map(fn ($id) => (int) $id)->merge($gerentes)->push($eu->id)->unique();

        $atuais = $conversa->participantes()->pluck('users.id');
        $conversa->participantes()->detach($atuais->diff($membros)->all());
        $conversa->participantes()->attach($membros->diff($atuais)->all());

        return redirect()->route('chat.show', $conversa)->with('msg', 'Grupo atualizado.');
    }

    /** Sair de um grupo */
    public function sair(Request $request, Conversation $conversa)
    {
        $eu = $request->user();
        $this->exigirParticipante($conversa, $eu);
        abort_unless($conversa->ehGrupo(), 404);

        $conversa->participantes()->detach($eu->id);

        return redirect()->route('chat.index')->with('msg', 'Você saiu do grupo "'.$conversa->nome.'".');
    }

    /** Envia uma mensagem (texto e/ou anexo). Pela tela, responde em JSON com o HTML da mensagem. */
    public function enviar(Request $request, Conversation $conversa)
    {
        $eu = $request->user();
        $this->exigirParticipante($conversa, $eu);

        $dados = $request->validate([
            'corpo' => ['nullable', 'string', 'max:4000', 'required_without:anexo'],
            'anexo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ], [
            'corpo.required_without' => 'Escreva uma mensagem ou escolha um arquivo.',
            'corpo.max' => 'A mensagem pode ter no máximo 4.000 caracteres.',
            'anexo.mimes' => 'O anexo deve ser imagem (JPG, PNG, WEBP) ou PDF.',
            'anexo.max' => 'O anexo pode ter no máximo 10 MB.',
        ]);

        $mensagem = new Message([
            'conversation_id' => $conversa->id,
            'user_id' => $eu->id,
            'corpo' => isset($dados['corpo']) ? trim($dados['corpo']) : null,
        ]);

        if ($request->hasFile('anexo')) {
            $arquivo = $request->file('anexo');
            $caminho = $arquivo->store(Message::PASTA, 'local');
            $mensagem->fill([
                'anexo_arquivo' => basename($caminho),
                'anexo_nome' => mb_substr($arquivo->getClientOriginalName(), 0, 255),
                'anexo_mime' => $arquivo->getMimeType() ?: 'application/octet-stream',
                'anexo_tamanho' => (int) $arquivo->getSize(),
            ]);
        }

        $mensagem->save();
        $conversa->update(['ultima_mensagem_em' => $mensagem->created_at]);
        $conversa->participantes()->updateExistingPivot($eu->id, ['ultima_lida_id' => $mensagem->id]);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $mensagem->id,
                'html' => view('chat._mensagem', ['mensagem' => $mensagem->load('user'), 'eu' => $eu, 'conversa' => $conversa])->render(),
            ]);
        }

        return redirect()->route('chat.show', $conversa);
    }

    /** Mensagens novas depois de ?depois=<id>. Chamado pela tela a cada poucos segundos. */
    public function novas(Request $request, Conversation $conversa)
    {
        $eu = $request->user();
        $this->exigirParticipante($conversa, $eu);

        $depois = (int) $request->query('depois', 0);
        $mensagens = $conversa->mensagens()->with('user')->where('id', '>', $depois)->limit(100)->get();

        if ($mensagens->isNotEmpty()) {
            $conversa->participantes()->updateExistingPivot($eu->id, ['ultima_lida_id' => $mensagens->last()->id]);
        }

        return response()->json([
            'mensagens' => $mensagens->map(fn (Message $m) => [
                'id' => $m->id,
                'html' => view('chat._mensagem', ['mensagem' => $m, 'eu' => $eu, 'conversa' => $conversa])->render(),
            ])->values(),
        ]);
    }

    /** Total de não lidas, para o número ao lado de "Chat" no menu */
    public function naoLidas(Request $request)
    {
        return response()->json(['total' => Conversation::naoLidasDe($request->user())]);
    }

    /** Abre o anexo de uma mensagem (só para quem está na conversa) */
    public function anexo(Request $request, Conversation $conversa, Message $mensagem)
    {
        $this->exigirParticipante($conversa, $request->user());
        abort_unless((int) $mensagem->conversation_id === (int) $conversa->id && $mensagem->temAnexo(), 404);

        $caminho = Message::PASTA.'/'.$mensagem->anexo_arquivo;
        abort_unless(Storage::disk('local')->exists($caminho), 404);

        $cabecalhos = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600'];

        if ($mensagem->anexoEhImagem()) {
            $cabecalhos['Content-Security-Policy'] = "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox";
        }

        return Storage::disk('local')->response($caminho, $mensagem->anexo_nome, $cabecalhos, 'inline');
    }

    // ------------------------------------------------------------------ Apoio

    private function tela(Request $request, ?Conversation $conversa)
    {
        $eu = $request->user();

        // Conversas da pessoa, com a última mensagem e quantas não leu em cada uma.
        // "nao_lidas" compara cada mensagem com a última lida guardada na tabela conversation_user.
        $conversas = $eu->conversas()
            ->with(['participantes', 'ultimaMensagem.user:id,name'])
            ->withCount(['mensagens as nao_lidas' => fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('messages.user_id')->orWhere('messages.user_id', '!=', $eu->id))
                ->whereRaw('messages.id > COALESCE(conversation_user.ultima_lida_id, 0)')])
            ->orderByDesc('ultima_mensagem_em')
            ->orderByDesc('conversations.id')
            ->get();

        $mensagens = collect();

        if ($conversa) {
            $conversa->load('participantes.sector:id,name');
            $mensagens = $conversa->mensagens()->with('user:id,name')->reorder()->orderByDesc('id')->limit(self::HISTORICO)->get()->reverse()->values();
            $conversa->marcarComoLida($eu);

            // Ao abrir, esta conversa deixa de ter não lidas na lista
            $conversas->firstWhere('id', $conversa->id)?->setAttribute('nao_lidas', 0);
        }

        return view('chat.index', [
            'eu' => $eu,
            'conversas' => $conversas,
            'conversa' => $conversa,
            'mensagens' => $mensagens,
            'pessoas' => User::ativos()->with('sector:id,name')->where('id', '!=', $eu->id)->orderBy('name')->get(['id', 'name', 'sector_id']),
            'podeCriarGrupo' => $eu->can('chat.grupos'),
            'podeGerenciar' => $conversa?->podeGerenciar($eu) ?? false,
        ]);
    }

    private function validarGrupo(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:80'],
            'membros' => ['required', 'array', 'min:1', 'max:200'],
            'membros.*' => ['integer', Rule::exists('users', 'id')->where('ativo', true)],
        ], [
            'nome.required' => 'Dê um nome ao grupo.',
            'membros.required' => 'Escolha pelo menos uma pessoa para o grupo.',
            'membros.*.exists' => 'Uma das pessoas escolhidas não está mais ativa.',
        ]);
    }

    /** Quem não está na conversa recebe "não encontrado", para nem saber que ela existe */
    private function exigirParticipante(Conversation $conversa, ?User $usuario): void
    {
        abort_unless($conversa->temParticipante($usuario), 404);
    }
}
