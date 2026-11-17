<?php

namespace App\Actions\Fortify;

use App\Models\AccessGroup;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // Domínios de e-mail autorizados (config/alpdesk.php). Lista vazia = cadastro aberto.
        $dominios = config('alpdesk.allowed_email_domains', []);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'bail', 'required', 'string', 'email', 'max:255', 'unique:users',
                function (string $attribute, mixed $value, Closure $fail) use ($dominios) {
                    if (empty($dominios)) {
                        return;
                    }

                    $dominio = strtolower(substr(strrchr($value, '@') ?: '', 1));

                    if (! in_array($dominio, $dominios, true)) {
                        $fail('Use um e-mail corporativo autorizado para criar sua conta.');
                    }
                },
            ],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        $usuario = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);

        // Toda pessoa que se cadastra entra no grupo padrão (config/permissoes.php)
        $slugPadrao = config('permissoes.grupo_padrao_cadastro');

        if ($slugPadrao) {
            $grupo = AccessGroup::where('slug', $slugPadrao)->first();

            if ($grupo) {
                $usuario->accessGroups()->syncWithoutDetaching([$grupo->id]);
            }
        }

        return $usuario;
    }
}
