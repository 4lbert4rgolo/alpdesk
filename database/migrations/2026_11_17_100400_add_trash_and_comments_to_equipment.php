<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - equipment.deleted_at: lixeira (o equipamento excluído fica guardado e pode ser restaurado).
     * - equipment_movements.tipo / comentario: o histórico passa a guardar também
     *   comentários, exclusões e restaurações.
     * - O grupo Técnico NÃO recebe a permissão de excluir; o Administrador já tem todas.
     */
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->string('tipo', 20)->default('alteracao')->index()->after('user_id');
            $table->text('comentario')->nullable()->after('alteracoes');
        });

        DB::table('equipment_movements')->where('descricao', 'Cadastrado')->update(['tipo' => 'cadastro']);
    }

    public function down(): void
    {
        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->dropIndex(['tipo']);
            $table->dropColumn(['tipo', 'comentario']);
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
