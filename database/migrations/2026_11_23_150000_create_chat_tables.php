<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat interno:
 * - conversations: conversa individual (2 pessoas) ou grupo (com nome);
 * - conversation_user: quem está na conversa, até qual mensagem já leu e se gerencia o grupo;
 * - messages: as mensagens (texto e/ou um anexo guardado em pasta privada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20);                 // individual | grupo
            $table->string('nome')->nullable();         // só grupos
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ultima_mensagem_em')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('conversation_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('ultima_lida_id')->nullable(); // última mensagem que a pessoa viu
            $table->boolean('gerente')->default(false);               // pode renomear e mudar membros do grupo
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('corpo')->nullable();
            $table->string('anexo_arquivo')->nullable();   // nome aleatório em storage/app/private/chat-anexos
            $table->string('anexo_nome')->nullable();      // nome original
            $table->string('anexo_mime', 100)->nullable();
            $table->unsignedInteger('anexo_tamanho')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_user');
        Schema::dropIfExists('conversations');
    }
};
