<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Cada usuário pode ter um setor e uma loja (onde trabalha).
 * - O equipamento pode ficar ligado à conta de quem usa (user_id).
 *   O campo de texto user_name continua para quem não tem login (ex.: "PC do caixa").
 *
 * nullOnDelete: se o setor, a loja ou o usuário for apagado, o vínculo vira "vazio"
 * em vez de dar erro ou apagar o registro junto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('sector_id')->nullable()->after('email')->constrained('sectors')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->after('sector_id')->constrained('stores')->nullOnDelete();
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('sector_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
            $table->dropConstrainedForeignId('sector_id');
        });
    }
};
