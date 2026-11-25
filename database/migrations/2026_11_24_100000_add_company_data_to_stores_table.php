<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Lojas" passam a ser "Empresas" (nas telas). Cada empresa guarda os dados que os
 * Termos de Uso e a Política de Privacidade (LGPD) pedem. O nome de sempre ("name")
 * vira o NOME FANTASIA. A tabela continua se chamando "stores" para não quebrar nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('razao_social')->nullable()->after('name');
            $table->string('cnpj', 18)->nullable()->unique()->after('razao_social'); // 00.000.000/0000-00
            $table->string('endereco')->nullable()->after('cnpj');
            $table->string('encarregado_nome')->nullable()->after('endereco');   // Encarregado (DPO) da LGPD
            $table->string('encarregado_email')->nullable()->after('encarregado_nome');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropUnique(['cnpj']);
            $table->dropColumn(['razao_social', 'cnpj', 'endereco', 'encarregado_nome', 'encarregado_email']);
        });
    }
};
