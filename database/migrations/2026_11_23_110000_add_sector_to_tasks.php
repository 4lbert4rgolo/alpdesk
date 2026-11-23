<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tarefas direcionadas a um setor (TI, Financeiro...).
 *
 * - sectors.recebe_tarefas: o setor aparece como destino ao abrir uma tarefa.
 * - tasks.sector_id: para qual setor a tarefa foi aberta.
 *
 * Dados: cria (ou reaproveita) os setores TI e Financeiro marcados como "recebe tarefas",
 * manda as tarefas antigas para TI e coloca no setor TI quem está no grupo Técnico
 * e ainda não tem setor (para os técnicos continuarem vendo as tarefas de TI).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->boolean('recebe_tarefas')->default(false)->after('name');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('sector_id')->nullable()->constrained('sectors')->nullOnDelete();
        });

        $ti = $this->setor('TI');
        $this->setor('Financeiro');

        DB::table('tasks')->whereNull('sector_id')->update(['sector_id' => $ti]);

        $grupoTecnico = DB::table('access_groups')->where('slug', 'tecnico')->value('id');

        if ($grupoTecnico) {
            $tecnicos = DB::table('access_group_user')->where('access_group_id', $grupoTecnico)->pluck('user_id');
            DB::table('users')->whereIn('id', $tecnicos)->whereNull('sector_id')->update(['sector_id' => $ti]);
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sector_id');
        });

        Schema::table('sectors', function (Blueprint $table) {
            $table->dropColumn('recebe_tarefas');
        });
    }

    /** Acha o setor pelo nome (sem diferenciar maiúsculas) ou cria; marca como "recebe tarefas". */
    private function setor(string $nome): int
    {
        $id = DB::table('sectors')->whereRaw('LOWER(name) = ?', [mb_strtolower($nome)])->value('id');

        if (! $id) {
            $id = DB::table('sectors')->insertGetId(['name' => $nome, 'created_at' => now(), 'updated_at' => now()]);
        }

        DB::table('sectors')->where('id', $id)->update(['recebe_tarefas' => true]);

        return (int) $id;
    }
};
