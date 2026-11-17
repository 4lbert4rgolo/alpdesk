<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - ativo: conta desativada não consegue entrar (e quem já estava logado é desconectado).
     *   Desativar é melhor do que excluir: as tarefas e o histórico continuam mostrando quem fez o quê.
     * - ultimo_acesso_em: data do último login, mostrada no painel de usuários.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('ativo')->default(true)->index()->after('password');
            $table->timestamp('ultimo_acesso_em')->nullable()->after('ativo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['ativo']);
            $table->dropColumn(['ativo', 'ultimo_acesso_em']);
        });
    }
};
