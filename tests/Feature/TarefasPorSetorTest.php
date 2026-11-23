<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Sector;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AvisoTarefa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tarefas direcionadas a um setor (TI, Financeiro): quem vê, quem assume, avisos e menu.
 */
class TarefasPorSetorTest extends TestCase
{
    use RefreshDatabase;

    private function setor(string $nome): Sector
    {
        return Sector::where('name', $nome)->firstOrFail(); // TI e Financeiro são criados pela migration
    }

    private function usuario(string $grupo, ?string $setor = null, array $extra = []): User
    {
        $usuario = User::factory()->create($extra);
        $usuario->accessGroups()->attach(AccessGroup::where('slug', $grupo)->firstOrFail()->id);

        if ($setor) {
            $usuario->forceFill(['sector_id' => $this->setor($setor)->id])->save();
        }

        return $usuario;
    }

    private function tarefa(User $dono, string $setor, string $titulo): Task
    {
        return Task::create([
            'title' => $titulo,
            'description' => 'Descrição',
            'deadline' => now()->addDays(2)->toDateString(),
            'priority' => 2,
            'items' => [],
            'user_id' => $dono->id,
            'sector_id' => $this->setor($setor)->id,
        ]);
    }

    public function test_migration_cria_ti_e_financeiro_recebendo_tarefas(): void
    {
        $this->assertTrue($this->setor('TI')->recebe_tarefas);
        $this->assertTrue($this->setor('Financeiro')->recebe_tarefas);
    }

    public function test_cada_setor_ve_so_as_proprias_tarefas_e_o_administrador_ve_todas(): void
    {
        $solicitante = $this->usuario('solicitante', 'TI');
        $this->tarefa($solicitante, 'TI', 'Impressora travada');
        $this->tarefa($solicitante, 'Financeiro', 'Boleto em atraso');

        $tecnicoTi = $this->usuario('tecnico', 'TI');
        $atendenteFin = $this->usuario('tecnico', 'Financeiro');
        $admin = $this->usuario('administrador');

        $this->actingAs($tecnicoTi)->get('/')->assertSee('Impressora travada')->assertDontSee('Boleto em atraso');
        $this->actingAs($atendenteFin)->get('/')->assertSee('Boleto em atraso')->assertDontSee('Impressora travada');
        $this->actingAs($admin)->get('/')->assertSee('Impressora travada')->assertSee('Boleto em atraso');
    }

    public function test_quem_abriu_continua_vendo_a_propria_tarefa_de_outro_setor(): void
    {
        $tecnicoTi = $this->usuario('tecnico', 'TI');
        $tarefa = $this->tarefa($tecnicoTi, 'Financeiro', 'Reembolso de viagem');

        $this->actingAs($tecnicoTi)->get('/tasks/'.$tarefa->id)->assertOk();
    }

    public function test_tecnico_nao_abre_tarefa_de_outro_setor(): void
    {
        $solicitante = $this->usuario('solicitante');
        $tarefa = $this->tarefa($solicitante, 'Financeiro', 'Nota fiscal');

        $this->actingAs($this->usuario('tecnico', 'TI'))->get('/tasks/'.$tarefa->id)->assertNotFound();
    }

    public function test_so_assume_quem_atende_o_setor_ou_o_administrador(): void
    {
        $solicitante = $this->usuario('solicitante');
        $tarefa = $this->tarefa($solicitante, 'Financeiro', 'Conferir pagamento');

        $this->actingAs($this->usuario('tecnico', 'TI'))->post('/tasks/join/'.$tarefa->id)->assertForbidden();

        $atendenteFin = $this->usuario('tecnico', 'Financeiro');
        $this->actingAs($atendenteFin)->post('/tasks/join/'.$tarefa->id)->assertRedirect('/tasks/'.$tarefa->id);
        $this->assertTrue($tarefa->fresh()->users->contains('id', $atendenteFin->id));

        $admin = $this->usuario('administrador');
        $this->actingAs($admin)->post('/tasks/join/'.$tarefa->id)->assertRedirect('/tasks/'.$tarefa->id);
    }

    public function test_abrir_tarefa_para_o_financeiro_avisa_so_o_financeiro(): void
    {
        Notification::fake();
        $solicitante = $this->usuario('solicitante');
        $tecnicoTi = $this->usuario('tecnico', 'TI');
        $atendenteFin = $this->usuario('tecnico', 'Financeiro');

        $this->actingAs($solicitante)->post('/tasks', [
            'title' => 'Segunda via de boleto',
            'description' => 'Cliente pediu',
            'deadline' => now()->addDay()->toDateString(),
            'priority' => '2',
            'sector_id' => $this->setor('Financeiro')->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->setor('Financeiro')->id, Task::firstOrFail()->sector_id);
        Notification::assertSentTo($atendenteFin, AvisoTarefa::class);
        Notification::assertNotSentTo($tecnicoTi, AvisoTarefa::class);
    }

    public function test_sem_setor_escolhido_vai_para_ti(): void
    {
        $this->actingAs($this->usuario('solicitante'))->post('/tasks', [
            'title' => 'Mouse quebrado',
            'description' => 'Não clica',
            'deadline' => now()->addDay()->toDateString(),
            'priority' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->setor('TI')->id, Task::firstOrFail()->sector_id);
    }

    public function test_nao_aceita_setor_que_nao_recebe_tarefas(): void
    {
        $vendas = Sector::create(['name' => 'Vendas']);

        $this->actingAs($this->usuario('solicitante'))->post('/tasks', [
            'title' => 'Teste',
            'description' => 'x',
            'deadline' => now()->addDay()->toDateString(),
            'priority' => '1',
            'sector_id' => $vendas->id,
        ])->assertSessionHasErrors('sector_id');
    }

    public function test_quem_abriu_encaminha_para_outro_setor(): void
    {
        Notification::fake();
        $solicitante = $this->usuario('solicitante');
        $atendenteFin = $this->usuario('tecnico', 'Financeiro');
        $tarefa = $this->tarefa($solicitante, 'TI', 'Era do financeiro');

        $this->actingAs($solicitante)->put('/tasks/update/'.$tarefa->id, [
            'title' => $tarefa->title,
            'description' => $tarefa->description,
            'deadline' => $tarefa->deadline->toDateString(),
            'priority' => '2',
            'sector_id' => $this->setor('Financeiro')->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->setor('Financeiro')->id, $tarefa->fresh()->sector_id);
        $this->assertDatabaseHas('task_activities', ['task_id' => $tarefa->id, 'tipo' => 'encaminhada']);
        Notification::assertSentTo($atendenteFin, AvisoTarefa::class);
    }

    public function test_menu_tem_uma_entrada_so_de_tarefas(): void
    {
        $this->actingAs($this->usuario('administrador'))->get('/')
            ->assertOk()
            ->assertDontSee('Tarefas · TI')
            ->assertDontSee('Tarefas · Financeiro');
    }

    public function test_filtro_por_setor_na_lista(): void
    {
        $solicitante = $this->usuario('solicitante');
        $this->tarefa($solicitante, 'TI', 'Rede lenta');
        $this->tarefa($solicitante, 'Financeiro', 'Fechamento do caixa');

        $this->actingAs($this->usuario('administrador'))
            ->get('/?setor='.$this->setor('Financeiro')->id)
            ->assertSee('Fechamento do caixa')
            ->assertDontSee('Rede lenta');
    }

    public function test_painel_do_setor_conta_so_as_tarefas_dele(): void
    {
        $solicitante = $this->usuario('solicitante');
        $this->tarefa($solicitante, 'TI', 'A');
        $this->tarefa($solicitante, 'Financeiro', 'B');
        $this->tarefa($solicitante, 'Financeiro', 'C');

        $this->actingAs($this->usuario('tecnico', 'Financeiro'))->get('/painel')
            ->assertOk()
            ->assertViewHas('kpis', fn ($kpis) => $kpis['total'] === 2);
    }
}
