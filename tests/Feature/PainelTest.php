<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PainelTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $grupo): User
    {
        $usuario = User::factory()->create();
        $usuario->accessGroups()->attach(AccessGroup::where('slug', $grupo)->firstOrFail()->id);

        // Técnicos atendem o setor TI (criado pela migration), como no sistema real
        if ($grupo === 'tecnico') {
            $usuario->forceFill(['sector_id' => \App\Models\Sector::where('name', 'TI')->value('id')])->save();
        }

        return $usuario;
    }

    private function tarefa(User $dono, array $extra = []): Task
    {
        $tarefa = Task::create(array_merge([
            'title' => 'Tarefa de teste',
            'description' => 'Descrição',
            'deadline' => now()->addDays(2)->toDateString(),
            'priority' => 2,
            'items' => [],
            'user_id' => $dono->id,
        ], $extra));

        return $tarefa;
    }

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get('/painel')->assertRedirect('/login');
    }

    public function test_solicitante_nao_acessa_o_painel(): void
    {
        $this->actingAs($this->usuario('solicitante'))->get('/painel')->assertForbidden();
    }

    public function test_tecnico_ve_os_indicadores(): void
    {
        $tecnico = $this->usuario('tecnico');
        $solicitante = $this->usuario('solicitante');

        $this->tarefa($solicitante, ['priority' => 4]);                                  // urgente, ninguém assumiu
        $this->tarefa($solicitante, ['deadline' => now()->subDays(3)->toDateString()]);  // vencida
        $assumida = $this->tarefa($solicitante);
        $assumida->users()->attach($tecnico->id);                                        // o técnico assumiu
        $this->tarefa($solicitante, [                                                    // concluída: fica fora dos números
            'priority' => 4,
            'status' => 'concluida',
            'concluida_em' => now(),
            'deadline' => now()->subDays(5)->toDateString(),
        ]);

        $this->actingAs($tecnico)->get('/painel')
            ->assertOk()
            ->assertSee('Painel')
            ->assertViewHas('kpis', fn (array $k) => $k['total'] === 3
                && $k['urgentes'] === 1
                && $k['vencidas'] === 1
                && $k['sem_responsavel'] === 2
                && $k['concluidas_periodo'] === 1);
    }

    public function test_periodo_invalido_volta_para_14_dias(): void
    {
        $this->actingAs($this->usuario('tecnico'))->get('/painel?dias=999')
            ->assertOk()
            ->assertViewHas('dias', 14);
    }
}
