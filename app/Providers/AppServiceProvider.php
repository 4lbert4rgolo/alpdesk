<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Permissoes;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
