<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Histórico do equipamento: cadastro, troca de loja, setor, usuário e situação.
     */
    public function up(): void
    {
        Schema::create('equipment_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('descricao');
            $table->json('alteracoes')->nullable(); // ['Loja' => ['antes', 'depois'], ...]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_movements');
    }
};
