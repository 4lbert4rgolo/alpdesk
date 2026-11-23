<?php

use App\Http\Middleware\BloquearContaDesativada;
use App\Http\Middleware\ExigirAceiteTermos;
use App\Http\Middleware\ExigirDoisFatoresAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Confia no proxy que fica na frente da aplicação (Nginx, Cloudflare Tunnel...)
        // somente quando a conexão vem da própria máquina ou de uma rede privada.
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '::1',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ]);

        $middleware->web(append: [SecurityHeaders::class, BloquearContaDesativada::class, ExigirDoisFatoresAdmin::class, ExigirAceiteTermos::class]);

        // Só responde para o endereço do APP_URL (ex.: helpdesk.suaempresa.com.br).
        // Impede que alguém troque o cabeçalho Host e faça os links dos e-mails
        // (como o de "redefinir senha") apontarem para um site falso.
        // Não vale no ambiente local nem nos testes.
        $middleware->trustHosts();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
