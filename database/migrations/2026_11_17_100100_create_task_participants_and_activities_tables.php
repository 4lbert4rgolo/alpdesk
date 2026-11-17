<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - task_participants: pessoas que acompanham a tarefa. Podem ver, comentar e
     *   registrar autorizações, mas não editam nem atendem a tarefa.
     * - task_activities: linha do tempo da tarefa (comentários, autorizações,
     *   mudanças de situação, quem assumiu, quem saiu etc.).
     */
    public function up(): void
    {
        Schema::create('task_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
        });

        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tipo', 40)->index();
            $table->text('corpo')->nullable();
            $table->json('dados')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activities');
        Schema::dropIfExists('task_participants');
    }
};
