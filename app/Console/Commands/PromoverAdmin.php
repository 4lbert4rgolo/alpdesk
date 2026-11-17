<?php

namespace App\Console\Commands;

use App\Models\AccessGroup;
use App\Models\User;
use Illuminate\Console\Command;

class PromoverAdmin extends Command
{
    protected $signature = 'alpdesk:admin {email : E-mail do usuário que será administrador}';

    protected $description = 'Coloca um usuário que já existe no grupo Administrador';

    public function handle(): int
    {
        $usuario = User::where('email', $this->argument('email'))->first();

        if (! $usuario) {
            $this->error('Nenhum usuário encontrado com esse e-mail.');

            return self::FAILURE;
        }

        $grupo = AccessGroup::where('slug', 'administrador')->first();

        if (! $grupo) {
            $this->error('O grupo Administrador não existe. Rode primeiro: php artisan migrate');

            return self::FAILURE;
        }

        $usuario->accessGroups()->syncWithoutDetaching([$grupo->id]);

        $this->info("{$usuario->name} agora é administrador.");

        return self::SUCCESS;
    }
}
