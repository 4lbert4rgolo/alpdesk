<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Um aceite dos Termos de Uso e da Política de Privacidade (LGPD).
 */
class TermAcceptance extends Model
{
    protected $fillable = [
        'user_id',
        'versao',
        'aceito_em',
        'ip',
        'navegador',
    ];

    protected function casts(): array
    {
        return [
            'aceito_em' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Registra que a pessoa aceitou a versão atual. Se já tinha aceitado, não duplica.
     */
    public static function registrar(User $usuario, ?Request $request = null): self
    {
        return static::firstOrCreate(
            ['user_id' => $usuario->id, 'versao' => (string) config('alpdesk.termos_versao')],
            [
                'aceito_em' => now(),
                'ip' => $request?->ip(),
                'navegador' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            ]
        );
    }
}
