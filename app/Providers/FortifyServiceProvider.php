<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
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
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // Login: confere e-mail e senha e bloqueia contas desativadas no painel de usuários.
        // O aviso de "conta desativada" só aparece depois da senha certa, para não revelar
        // a quem tenta adivinhar quais e-mails existem no sistema.
        Fortify::authenticateUsing(function (Request $request) {
            // Compara o e-mail sem diferenciar maiúsculas de minúsculas
            $email = mb_strtolower(trim((string) $request->input(Fortify::username())));
            $usuario = User::whereRaw('LOWER(email) = ?', [$email])->first();

            if (! $usuario || ! Hash::check((string) $request->input('password'), $usuario->password)) {
                return null;
            }

            if (! $usuario->ativo) {
                throw ValidationException::withMessages([
                    Fortify::username() => 'Sua conta está desativada. Fale com o administrador do sistema.',
                ]);
            }

            return $usuario;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
