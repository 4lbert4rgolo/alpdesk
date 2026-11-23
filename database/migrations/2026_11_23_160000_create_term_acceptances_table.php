<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro dos aceites dos Termos de Uso e da Política de Privacidade (LGPD).
     *
     * Uma linha por aceite (não um campo em "users"), para guardar o HISTÓRICO:
     * se a versão mudar, a pessoa aceita de novo e as duas linhas ficam guardadas.
     * É a prova de quem aceitou, qual versão, quando e de onde.
     */
    public function up(): void
    {
        Schema::create('term_acceptances', function (Blueprint $table) {
            $table->id();
            // Contas não são apagadas no AlpDesk (são desativadas), então o registro fica guardado
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('versao', 40);
            $table->timestamp('aceito_em');
            $table->string('ip', 45)->nullable();          // 45 caracteres cabem um IPv6
            $table->string('navegador', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'versao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_acceptances');
    }
};
