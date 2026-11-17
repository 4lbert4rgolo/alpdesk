<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Se o administrador desativar uma conta, quem estiver logado com ela é
 * desconectado na próxima página que abrir.
 */
class BloquearContaDesativada
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->ativo) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors([
                'email' => 'Sua conta foi desativada. Fale com o administrador do sistema.',
            ]);
        }

        return $next($request);
    }
}
