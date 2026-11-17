<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Até agora, quem abria a tarefa entrava automaticamente como responsável.
     * A partir de agora "quem abriu" e "responsável" são coisas separadas.
     *
     * Aqui saem da lista de responsáveis as pessoas que abriram a tarefa e que
     * NÃO podem atender tarefas (por exemplo, o grupo Solicitante). Quem abriu e
     * também pode atender (Técnico, Administrador) continua responsável, porque
     * não dá para saber se essa pessoa estava mesmo cuidando da tarefa.
     *
     * Depois disso, tarefas que têm algum responsável ficam "Em andamento".
     */
    public function up(): void
    {
        $grupos = DB::table('access_groups')->get()->keyBy('id');

        $podeAtender = function (int $usuarioId) use ($grupos): bool {
            $idsGrupos = DB::table('access_group_user')->where('user_id', $usuarioId)->pluck('access_group_id');

            foreach ($idsGrupos as $idGrupo) {
                $grupo = $grupos[$idGrupo] ?? null;

                if (! $grupo) {
                    continue;
                }

                $permissoes = json_decode($grupo->permissions ?? '[]', true) ?: [];

                if ($grupo->slug === 'administrador'
                    || in_array('tarefas.assumir', $permissoes, true)
                    || in_array('tarefas.gerenciar_todas', $permissoes, true)) {
                    return true;
                }
            }

            return false;
        };

        $cache = [];

        DB::table('tasks')->select('id', 'user_id')->orderBy('id')->chunk(200, function ($tarefas) use (&$cache, $podeAtender) {
            foreach ($tarefas as $tarefa) {
                if (! $tarefa->user_id) {
                    continue;
                }

                $cache[$tarefa->user_id] ??= $podeAtender((int) $tarefa->user_id);

                if (! $cache[$tarefa->user_id]) {
                    DB::table('task_user')
                        ->where('task_id', $tarefa->id)
                        ->where('user_id', $tarefa->user_id)
                        ->delete();
                }
            }
        });

        $comResponsavel = DB::table('task_user')->distinct()->pluck('task_id');

        foreach ($comResponsavel->chunk(500) as $lote) {
            DB::table('tasks')
                ->whereIn('id', $lote->all())
                ->where('status', 'aberta')
                ->update(['status' => 'em_andamento']);
        }
    }

    public function down(): void
    {
        // Nada a desfazer: os vínculos removidos eram automáticos.
    }
};
