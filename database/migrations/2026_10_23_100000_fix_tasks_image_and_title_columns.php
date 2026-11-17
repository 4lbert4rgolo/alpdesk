<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - image: a imagem da tarefa é opcional, então a coluna precisa aceitar vazio.
     * - title: o formulário aceita até 255 caracteres, mas a coluna só guardava 60.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('image')->nullable()->change();
            $table->string('title', 255)->change();
        });
    }

    public function down(): void
    {
        // Nada a desfazer: voltar atrás travaria tarefas sem imagem e cortaria títulos longos.
    }
};
