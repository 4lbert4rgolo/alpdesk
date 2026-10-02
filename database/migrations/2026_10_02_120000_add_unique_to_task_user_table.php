<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove responsáveis duplicados (mesma tarefa + mesmo usuário) e
     * impede que o problema volte a acontecer.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $pares = DB::table('task_user')
                ->select('task_id', 'user_id', DB::raw('MIN(created_at) as created_at'), DB::raw('MAX(updated_at) as updated_at'))
                ->groupBy('task_id', 'user_id')
                ->get()
                ->map(fn ($linha) => (array) $linha)
                ->all();

            DB::table('task_user')->delete();

            foreach (array_chunk($pares, 500) as $lote) {
                DB::table('task_user')->insert($lote);
            }
        });

        Schema::table('task_user', function (Blueprint $table) {
            $table->unique(['task_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('task_user', function (Blueprint $table) {
            $table->dropUnique(['task_id', 'user_id']);
        });
    }
};
