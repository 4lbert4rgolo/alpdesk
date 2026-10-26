<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('access_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_group_id')->constrained('access_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['access_group_id', 'user_id']);
        });

        // Grupos iniciais
        $agora = now();

        DB::table('access_groups')->insert([
            [
                'name' => 'Administrador',
                'slug' => 'administrador',
                'description' => 'Acesso total ao sistema, inclusive usuários e grupos.',
                'permissions' => json_encode([]),
                'is_system' => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ],
            [
                'name' => 'Técnico',
                'slug' => 'tecnico',
                'description' => 'Abre tarefas e assume as que vai resolver.',
                'permissions' => json_encode(['tarefas.criar', 'tarefas.assumir']),
                'is_system' => false,
                'created_at' => $agora,
                'updated_at' => $agora,
            ],
            [
                'name' => 'Solicitante',
                'slug' => 'solicitante',
                'description' => 'Abre tarefas e acompanha o andamento.',
                'permissions' => json_encode(['tarefas.criar']),
                'is_system' => false,
                'created_at' => $agora,
                'updated_at' => $agora,
            ],
        ]);

        // Quem já tinha conta continua podendo abrir e assumir tarefas (grupo Técnico)
        $tecnico = DB::table('access_groups')->where('slug', 'tecnico')->value('id');

        $vinculos = DB::table('users')->pluck('id')
            ->map(fn ($id) => [
                'access_group_id' => $tecnico,
                'user_id' => $id,
                'created_at' => $agora,
                'updated_at' => $agora,
            ])
            ->all();

        foreach (array_chunk($vinculos, 500) as $lote) {
            DB::table('access_group_user')->insert($lote);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('access_group_user');
        Schema::dropIfExists('access_groups');
    }
};
