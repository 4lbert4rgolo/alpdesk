<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Models\HelpArticle;
use App\Models\User;
use App\Support\Permissoes;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Cada permissão de config/permissoes.php vira uma habilidade do Laravel,
        // usada em @can('usuarios.gerenciar') e nas rotas com can:usuarios.gerenciar.
        foreach (Permissoes::chaves() as $permissao) {
            Gate::define($permissao, fn (User $usuario) => $usuario->hasPermission($permissao));
        }

        // Guarda a data do último acesso (mostrada no painel de usuários)
        Event::listen(Login::class, function (Login $evento) {
            if ($evento->user instanceof User) {
                $evento->user->forceFill(['ultimo_acesso_em' => now()])->saveQuietly();
            }
        });

        // Menu lateral: número de mensagens não lidas no chat
        View::composer('layouts.main', function ($view) {
            $view->with('chatNaoLidas', Conversation::naoLidasDe(auth()->user()));

            // Menu lateral: circulares que a pessoa ainda precisa confirmar ("Li e estou ciente")
            $usuario = auth()->user();
            $view->with('circularesPendentes', $usuario
                ? HelpArticle::query()->visivelPara($usuario)->pendentesDeCiencia($usuario)->count()
                : 0);
        });

        // Em produção atrás de um proxy HTTPS, todos os links saem em https
        if (config('alpdesk.force_https')) {
            URL::forceScheme('https');
        }

        // Regra de senha: mais exigente em produção
        Password::defaults(fn () => app()->isProduction()
            ? Password::min(10)->mixedCase()->numbers()
            : Password::min(8));
    }
}
