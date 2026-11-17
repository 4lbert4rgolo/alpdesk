<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Com ADMIN_2FA_REQUIRED=true, quem é administrador e ainda não ativou a verificação
 * em duas etapas só consegue usar a tela de perfil (para ativá-la) e sair do sistema.
 * Protege as contas que mais podem fazer estrago se a senha vazar.
 */
class ExigirDoisFatoresAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! config('alpdesk.exigir_2fa_admin')
            || ! $usuario
            || ! $usuario->isAdministrator()
            || $usuario->temDoisFatores()
            || $this->liberado($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Ative a verificação em duas etapas no seu perfil.');
        }

        return redirect('/user/profile')->with(
            'msg',
            'Administradores precisam ativar a verificação em duas etapas antes de continuar. Ative abaixo, em "Verificação em duas etapas".'
        );
    }

    /**
     * Telas que continuam liberadas: perfil e verificação em duas etapas (Fortify/Jetstream),
     * confirmação de senha, Livewire (usado pelo perfil) e sair.
     */
    private function liberado(Request $request): bool
    {
        return $request->is('user', 'user/*', 'logout', 'two-factor-challenge', 'livewire/*', 'livewire-*')
            || $request->routeIs('livewire.*', 'profile.show', 'logout', 'password.confirm*', 'two-factor.*');
    }
}
