<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O grupo Técnico passa a ver o painel (se o grupo existir).
     * O Administrador já tem todas as permissões.
     */
    public function up(): void
    {
        if (! Schema::hasTable('access_groups')) {
            return;
        }

        $grupo = DB::table('access_groups')->where('slug', 'tecnico')->first();

        if (! $grupo) {
            return;
        }

        $atuais = json_decode($grupo->permissions ?? '[]', true) ?: [];
        $novas = array_values(array_unique(array_merge($atuais, ['painel.ver'])));

        DB::table('access_groups')->where('id', $grupo->id)->update([
            'permissions' => json_encode($novas),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Nada a desfazer: as permissões podem ser ajustadas na tela de grupos.
    }
};
