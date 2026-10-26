<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Quem já tinha conta antes de ligarmos a verificação de e-mail
     * passa a constar como verificado, para não ser barrado ao entrar.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Nada a desfazer.
    }
};
