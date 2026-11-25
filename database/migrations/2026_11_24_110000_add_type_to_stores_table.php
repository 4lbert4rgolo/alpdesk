<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nem todo cadastro de "Empresa" é uma empresa de verdade (com CNPJ). Um "Escritório",
 * por exemplo, é só um LOCAL: serve para equipamentos e pessoas, mas não é a
 * controladora dos dados na LGPD.
 *
 * - tipo: "empresa" (tem CNPJ, aparece no termo) ou "local" (sem CNPJ).
 * - empresa_responsavel_id: para um local, qual empresa responde pelos dados de quem
 *   trabalha nele. O termo dessas pessoas usa os dados dessa empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('tipo', 10)->default('empresa')->after('name');
            $table->foreignId('empresa_responsavel_id')->nullable()->after('tipo')
                ->constrained('stores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_responsavel_id');
            $table->dropColumn('tipo');
        });
    }
};
