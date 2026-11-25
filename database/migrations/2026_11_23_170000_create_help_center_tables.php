<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central de Ajuda: perguntas frequentes, tutoriais, circulares e documentos.
 *
 * - help_articles: cada conteúdo publicado. "tipo" diz se é FAQ, tutorial, circular ou documento.
 * - help_article_sector / help_article_store: para QUEM o conteúdo aparece.
 *   Nenhum setor marcado = todos os setores; nenhuma loja marcada = todas as lojas.
 * - help_attachments: arquivos do conteúdo (PDF, Word, Excel, imagens), em pasta privada.
 * - help_reads: confirmações de leitura das circulares ("Li e estou ciente").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20)->index();             // faq, tutorial, circular, documento
            $table->string('titulo');
            $table->string('resumo', 300)->nullable();       // frase curta mostrada na lista
            $table->longText('conteudo')->nullable();        // texto (aceita **negrito**, listas e links)
            $table->string('categoria', 80)->nullable()->index(); // ex.: "Sistema de vendas", "RH"
            $table->boolean('exige_ciencia')->default(false); // circular: pede "Li e estou ciente"
            $table->boolean('fixado')->default(false);       // aparece no topo da lista
            $table->boolean('publicado')->default(true);     // false = rascunho (só quem gerencia vê)
            $table->timestamp('publicado_em')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();         // quem criou
            $table->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('help_article_sector', function (Blueprint $table) {
            $table->foreignId('help_article_id')->constrained('help_articles')->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained('sectors')->cascadeOnDelete();
            $table->primary(['help_article_id', 'sector_id']);
        });

        Schema::create('help_article_store', function (Blueprint $table) {
            $table->foreignId('help_article_id')->constrained('help_articles')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->primary(['help_article_id', 'store_id']);
        });

        Schema::create('help_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_article_id')->constrained('help_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nome_original');
            $table->string('arquivo');          // nome aleatório em storage/app/private/help-attachments
            $table->string('mime', 150);
            $table->unsignedInteger('tamanho'); // em bytes
            $table->timestamps();
        });

        Schema::create('help_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_article_id')->constrained('help_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('lido_em');
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->unique(['help_article_id', 'user_id']); // cada pessoa confirma uma vez
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_reads');
        Schema::dropIfExists('help_attachments');
        Schema::dropIfExists('help_article_store');
        Schema::dropIfExists('help_article_sector');
        Schema::dropIfExists('help_articles');
    }
};
