<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * LGPD: quem está logado e ainda não aceitou a versão atual dos Termos de Uso e da
 * Política de Privacidade (config/alpdesk.php > termos_versao) é levado para a tela
 * de aceite. Até aceitar, só consegue ler os textos, confirmar o e-mail e sair.
 *
 * Vale para todo mundo: quem se cadastrou sozinho, quem foi criado pelo TI no painel
 * de usuários e quem já usava o sistema antes desta tela existir.
 */
class ExigirAceiteTermos
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! config('alpdesk.exigir_aceite_termos') || ! $usuario || $this->liberado($request)) {
            return $next($request);
        }

        // A sessão lembra que a pessoa já aceitou esta versão, para não consultar
        // o banco a cada clique. A chave inclui o id: trocar de conta exige conferir de novo.
        $versao = (string) config('alpdesk.termos_versao');
        $chave = 'termos_aceitos.'.$usuario->id;

        if ($request->session()->get($chave) === $versao) {
            return $next($request);
        }

        if ($usuario->aceitouTermosAtuais()) {
            $request->session()->put($chave, $versao);

            return $next($request);
        }

        // Pedidos feitos por JavaScript (ex.: contador do chat) recebem um erro simples
        if ($request->expectsJson()) {
            abort(403, 'Aceite os Termos de Uso e a Política de Privacidade para continuar.');
        }

        // Guarda a tela que a pessoa queria abrir, para voltar a ela depois do aceite
        if ($request->isMethod('GET')) {
            session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('termos.aceite');
    }

    /**
     * Telas que continuam liberadas antes do aceite: a própria tela de aceite, os textos
     * dos Termos e da Política, confirmação de e-mail, verificação em duas etapas e sair.
     *
     * O perfil também fica liberado de propósito: o ExigirDoisFatoresAdmin manda o
     * administrador sem 2FA para o perfil. Se o perfil fosse bloqueado aqui, os dois
     * middlewares ficariam mandando a pessoa de um lado para o outro sem parar.
     */
    private function liberado(Request $request): bool
    {
        return $request->routeIs(
            'termos.*', 'terms.show', 'policy.show', 'logout', 'profile.show',
            'verification.*', 'two-factor.*', 'password.confirm*', 'livewire.*'
        ) || $request->is('logout', 'user', 'user/*', 'two-factor-challenge', 'email/*', 'livewire/*', 'livewire-*');
    }
}
