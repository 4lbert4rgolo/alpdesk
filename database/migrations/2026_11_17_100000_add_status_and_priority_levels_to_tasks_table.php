<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - status: situação da tarefa (aberta, em andamento, aguardando, concluída, cancelada).
     * - concluida_em / concluida_por / solucao: quem encerrou, quando e o que foi feito.
     * - priority: deixa de ser sim/não e passa a ter níveis de 1 (Baixa) a 4 (Urgente).
     *   As tarefas antigas marcadas como urgentes viram 4; as normais viram 2 (Média).
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('status', 20)->default('aberta')->index()->after('priority');
            $table->timestamp('concluida_em')->nullable()->after('status');
            $table->foreignId('concluida_por')->nullable()->after('concluida_em')
                ->constrained('users')->nullOnDelete();
            $table->text('solucao')->nullable()->after('concluida_por');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedTinyInteger('priority')->default(2)->change();
        });

        // A ordem importa: primeiro 1 -> 4, depois 0 -> 2
        DB::table('tasks')->where('priority', 1)->update(['priority' => 4]);
        DB::table('tasks')->where('priority', 0)->update(['priority' => 2]);
    }

    public function down(): void
    {
        DB::table('tasks')->where('priority', '<', 4)->update(['priority' => 0]);
        DB::table('tasks')->where('priority', 4)->update(['priority' => 1]);

        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('priority')->default(false)->change();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('concluida_por');
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'concluida_em', 'solucao']);
        });
    }
};
