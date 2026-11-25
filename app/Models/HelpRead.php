<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Confirmação de leitura de uma circular ("Li e estou ciente"): quem, quando e de onde.
 */
class HelpRead extends Model
{
    protected $fillable = ['help_article_id', 'user_id', 'lido_em', 'ip'];

    protected function casts(): array
    {
        return [
            'lido_em' => 'datetime',
        ];
    }

    public function artigo(): BelongsTo
    {
        return $this->belongsTo(HelpArticle::class, 'help_article_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
