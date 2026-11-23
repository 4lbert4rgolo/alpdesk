<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pessoas definidas no modelo de tarefa:
 * - responsaveis: quem já recebe a tarefa como responsável quando ela é aberta pelo modelo
 *   (pessoas do setor do modelo que podem atender tarefas);
 * - participantes: quem já vem marcado como participante no formulário (dá para mudar na abertura).
 * Guardados como listas de ids de usuário (JSON).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->json('responsaveis')->nullable()->after('prioridade');
            $table->json('participantes')->nullable()->after('responsaveis');
        });
    }

    public function down(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->dropColumn(['responsaveis', 'participantes']);
        });
    }
};
