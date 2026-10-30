<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Equipment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TarefasTelasTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $grupo): User
    {
        $usuario = User::factory()->create();
        $usuario->accessGroups()->attach(AccessGroup::where('slug', $grupo)->firstOrFail()->id);

        return $usuario;
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'title' => 'Impressora do caixa parada',
            'description' => 'Não imprime desde ontem.',
            'deadline' => now()->addDays(2)->toDateString(),
            'priority' => '1',
        ], $extra);
    }

    private function tarefa(User $dono, string $titulo): Task
    {
        $tarefa = Task::create([
            'title' => $titulo,
            'description' => 'Descrição',
            'deadline' => now()->addDays(2)->toDateString(),
            'priority' => 0,
            'items' => [],
            'user_id' => $dono->id,
        ]);

        $tarefa->users()->attach($dono->id);

        return $tarefa;
    }

    public function test_formulario_mantem_o_que_foi_preenchido_quando_ha_erro(): void
    {
        $usuario = $this->usuario('solicitante');

        $this->actingAs($usuario)
            ->from('/tasks/create')
            ->followingRedirects()
            ->post('/tasks', $this->dados(['description' => '', 'items' => ['Autorizo acesso remoto']]))
            ->assertOk()
            ->assertSee('Revise os campos destacados')
            ->assertSee('Impressora do caixa parada')
            ->assertSee('value="'.now()->addDays(2)->toDateString().'"', false);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_prazo_que_ja_passou_e_recusado_ao_abrir_tarefa(): void
    {
        $usuario = $this->usuario('solicitante');

        $this->actingAs($usuario)
            ->post('/tasks', $this->dados(['deadline' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors('deadline');
    }

    public function test_equipamento_fora_da_informatica_e_recusado(): void
    {
        $usuario = $this->usuario('solicitante');
        $cadeira = Equipment::create(['name' => 'Cadeira', 'type' => 'Mobiliário', 'status' => 'em_uso']);

        $this->actingAs($usuario)
            ->post('/tasks', $this->dados(['equipment_id' => $cadeira->id]))
            ->assertSessionHasErrors('equipment_id');
    }

    public function test_abrir_tarefa_leva_para_a_pagina_dela(): void
    {
        $usuario = $this->usuario('solicitante');

        $this->actingAs($usuario)->post('/tasks', $this->dados());

        $tarefa = Task::firstOrFail();

        $this->assertDatabaseHas('task_user', ['task_id' => $tarefa->id, 'user_id' => $usuario->id]);
        $this->actingAs($usuario)->get('/tasks/'.$tarefa->id)->assertOk()->assertSee('Impressora do caixa parada');
    }

    public function test_minhas_tarefas_junta_as_que_abri_e_as_que_sou_responsavel(): void
    {
        $eu = $this->usuario('tecnico');
        $outro = $this->usuario('solicitante');
        $minha = $this->tarefa($eu, 'Minha tarefa de teste');
        $dele = $this->tarefa($outro, 'Tarefa aberta por outra pessoa');
        $dele->users()->attach($eu->id);

        $this->actingAs($eu)->get('/dashboard')
            ->assertOk()
            ->assertSee('Minha tarefa de teste')
            ->assertSee('Tarefa aberta por outra pessoa');
    }

    public function test_responsavel_pode_sair_da_tarefa_mas_quem_abriu_nao(): void
    {
        $eu = $this->usuario('tecnico');
        $outro = $this->usuario('solicitante');
        $minha = $this->tarefa($eu, 'Minha tarefa');
        $dele = $this->tarefa($outro, 'Tarefa de outra pessoa');
        $dele->users()->attach($eu->id);

        $this->actingAs($eu)->post('/tasks/leave/'.$dele->id)->assertRedirect('/dashboard');
        $this->assertDatabaseMissing('task_user', ['task_id' => $dele->id, 'user_id' => $eu->id]);

        $this->actingAs($eu)->post('/tasks/leave/'.$minha->id)->assertForbidden();
    }
}
