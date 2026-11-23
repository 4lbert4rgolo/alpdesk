<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;

/**
 * Mensagem do chat interno: texto e/ou um anexo (imagem ou PDF).
 */
class Message extends Model
{
    public const PASTA = 'chat-anexos';

    protected $fillable = ['conversation_id', 'user_id', 'corpo', 'anexo_arquivo', 'anexo_nome', 'anexo_mime', 'anexo_tamanho'];

    public function conversa(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function temAnexo(): bool
    {
        return (bool) $this->anexo_arquivo;
    }

    public function anexoEhImagem(): bool
    {
        return str_starts_with((string) $this->anexo_mime, 'image/');
    }

    /**
     * Texto pronto para a tela: escapado (ninguém consegue injetar HTML), com quebras de linha
     * e com "#123" virando link para a tarefa 123.
     */
    public function corpoFormatado(): HtmlString
    {
        $seguro = e((string) $this->corpo);
        $comLinks = preg_replace('/(^|[\s(])#(\d{1,7})\b/u', '$1<a href="/tasks/$2">#$2</a>', $seguro);

        return new HtmlString(nl2br($comLinks));
    }
}
