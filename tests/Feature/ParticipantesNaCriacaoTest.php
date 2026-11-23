<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Sector;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Notifications\AvisoTarefa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Participantes escolhidos já ao abrir a tarefa, e editar/remover modelos pela lista.
 */
class ParticipantesNaCriacaoTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $grupo, array $extra = []): User
    {
        $usuario = User::factory()->create($extra);
        $usuario->accessGroups()->attach(AccessGroup::where('slug', $grupo)->firstOrFail()->id);

        return $usuario;
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'title' => 'Compra de cadeira',
            'description' => 'Cadeira quebrada',
            'deadline' => now()->addDays(2)->toDateString(),
            'priority' => '2',
        ], $extra);
    }

    public function test_formulario_mostra_as_pessoas_para_convidar(): void
    {
        $this->usuario('solicitante', ['name' => 'Gerente Paulo']);

        $this->actingAs($this->usuario('solicitante'))->get('/tasks/create?livre=1')
            ->assertOk()
            ->assertSee('Participantes')
            ->assertSee('Gerente Paulo');
    }

    public function test_quem_abre_ja_escolhe_os_participantes(): void
    {
        Notification::fake();
        $dono = $this->usuario('solicitante');
        $gerente = $this->usuario('solicitante');

        $this->actingAs($dono)->post('/tasks', $this->dados(['participantes' => [$gerente->id, $dono->id]]))
            ->assertSessionHasNoErrors();

        $tarefa = Task::firstOrFail();
        $this->assertTrue($tarefa->participantes->contains('id', $gerente->id));
        $this->assertFalse($tarefa->participantes->contains('id', $dono->id), 'Quem abriu não vira participante');
        $this->assertDatabaseHas('task_activities', ['task_id' => $tarefa->id, 'tipo' => 'participante_adicionado']);
        Notification::assertSentTo($gerente, AvisoTarefa::class);

        // O participante passa a ver a tarefa, mas não é responsável
        $this->actingAs($gerente)->get('/tasks/'.$tarefa->id)->assertOk();
        $this->assertFalse($tarefa->ehResponsavel($gerente));
    }

    public function test_nao_aceita_conta_desativada_como_participante(): void
    {
        $inativo = $this->usuario('solicitante');
        $inativo->forceFill(['ativo' => false])->save();

        $this->actingAs($this->usuario('solicitante'))
            ->post('/tasks', $this->dados(['participantes' => [$inativo->id]]))
            ->assertSessionHasErrors('participantes.0');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_remover_modelo_pela_lista_mantem_as_tarefas(): void
    {
        $admin = $this->usuario('administrador');
        $modelo = TaskTemplate::where('name', 'Cancelamento de nota fiscal')->firstOrFail();

        $this->actingAs($admin)->get('/admin/modelos')->assertOk()->assertSee('Remover')->assertSee('Editar');

        $this->actingAs($this->usuario('solicitante'))->post('/tasks', [
            'task_template_id' => $modelo->id,
            'title' => 'Cancelar NF 123',
            'deadline' => now()->addDay()->toDateString(),
            'priority' => '2',
            'respostas' => ['numero_nf' => '123', 'codigo_cliente' => '555', 'valor' => '10,00'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->delete('/admin/modelos/'.$modelo->id)->assertRedirect('/admin/modelos');

        $this->assertNull(TaskTemplate::find($modelo->id));
        $tarefa = Task::firstOrFail();
        $this->assertNull($tarefa->task_template_id);
        $this->assertSame('123', collect($tarefa->respostas)->firstWhere('chave', 'numero_nf')['valor']);
    }

    public function test_editar_modelo_muda_o_nome(): void
    {
        $admin = $this->usuario('administrador');
        $modelo = TaskTemplate::where('name', 'Cancelamento de nota fiscal')->firstOrFail();

        $this->actingAs($admin)->put('/admin/modelos/'.$modelo->id, [
            'name' => 'Cancelamento de NF',
            'sector_id' => $modelo->sector_id,
            'ativo' => '1',
            'campos' => collect($modelo->campos)->map(fn ($c) => $c + ['opcoes' => ''])->map(function ($c) {
                $c['opcoes'] = implode("\n", (array) $c['opcoes']);
                $c['obrigatorio'] = $c['obrigatorio'] ? '1' : null;

                return $c;
            })->all(),
        ])->assertSessionHasNoErrors();

        $this->assertSame('Cancelamento de NF', $modelo->fresh()->name);
        $this->assertSame('numero_nf', $modelo->fresh()->campos[0]['chave'], 'A chave do campo não muda ao editar');
    }

    // ------------------------------------------------ Pessoas definidas no modelo

    private function atendenteDo(string $setor): User
    {
        $pessoa = $this->usuario('tecnico');
        $pessoa->forceFill(['sector_id' => Sector::where('name', $setor)->value('id')])->save();

        return $pessoa;
    }

    private function camposDoModelo(TaskTemplate $modelo): array
    {
        return collect($modelo->campos)->map(function ($c) {
            $c['opcoes'] = implode("\n", (array) ($c['opcoes'] ?? []));
            $c['obrigatorio'] = $c['obrigatorio'] ? '1' : null;

            return $c;
        })->all();
    }

    public function test_modelo_atribui_a_tarefa_ao_responsavel_do_setor(): void
    {
        Notification::fake();
        $admin = $this->usuario('administrador');
        $ana = $this->atendenteDo('Financeiro');
        $outroDoFinanceiro = $this->atendenteDo('Financeiro');
        $gerente = $this->usuario('solicitante');
        $modelo = TaskTemplate::where('name', 'Cancelamento de nota fiscal')->firstOrFail();

        $this->actingAs($admin)->put('/admin/modelos/'.$modelo->id, [
            'name' => $modelo->name,
            'sector_id' => $modelo->sector_id,
            'ativo' => '1',
            'responsaveis' => [$ana->id],
            'participantes' => [$gerente->id],
            'campos' => $this->camposDoModelo($modelo),
        ])->assertSessionHasNoErrors();

        $solicitante = $this->usuario('solicitante');

        // O formulário mostra o responsável e já marca o participante sugerido
        $this->actingAs($solicitante)->get('/tasks/create?modelo='.$modelo->id)
            ->assertOk()
            ->assertSee($ana->name)
            ->assertSee('name="participantes[]" value="'.$gerente->id.'" checked', false);

        $this->actingAs($solicitante)->post('/tasks', [
            'task_template_id' => $modelo->id,
            'title' => 'Cancelar NF 77',
            'deadline' => now()->addDay()->toDateString(),
            'priority' => '2',
            'respostas' => ['numero_nf' => '77', 'codigo_cliente' => '9', 'valor' => '5,00'],
            'participantes' => [$gerente->id, $ana->id],
        ])->assertSessionHasNoErrors();

        $tarefa = Task::firstOrFail();
        $this->assertTrue($tarefa->ehResponsavel($ana));
        $this->assertTrue($tarefa->participantes->contains('id', $gerente->id));
        $this->assertFalse($tarefa->participantes->contains('id', $ana->id), 'O responsável não entra também como participante');
        $this->assertDatabaseHas('task_activities', ['task_id' => $tarefa->id, 'tipo' => 'atribuida']);

        Notification::assertSentTo($ana, AvisoTarefa::class);
        Notification::assertNotSentTo($outroDoFinanceiro, AvisoTarefa::class);
    }

    public function test_responsavel_do_modelo_precisa_ser_do_setor(): void
    {
        $admin = $this->usuario('administrador');
        $daTi = $this->atendenteDo('TI');
        $modelo = TaskTemplate::where('name', 'Cancelamento de nota fiscal')->firstOrFail();

        $this->actingAs($admin)->put('/admin/modelos/'.$modelo->id, [
            'name' => $modelo->name,
            'sector_id' => $modelo->sector_id,
            'ativo' => '1',
            'responsaveis' => [$daTi->id],
            'campos' => $this->camposDoModelo($modelo),
        ])->assertSessionHasErrors('responsaveis');
    }

    public function test_quem_mudou_de_setor_deixa_de_receber_pelo_modelo(): void
    {
        $ana = $this->atendenteDo('Financeiro');
        $modelo = TaskTemplate::where('name', 'Cancelamento de nota fiscal')->firstOrFail();
        $modelo->update(['responsaveis' => [$ana->id]]);

        $ana->forceFill(['sector_id' => Sector::where('name', 'TI')->value('id')])->save();

        $this->actingAs($this->usuario('solicitante'))->post('/tasks', [
            'task_template_id' => $modelo->id,
            'title' => 'Cancelar NF 78',
            'deadline' => now()->addDay()->toDateString(),
            'priority' => '2',
            'respostas' => ['numero_nf' => '78', 'codigo_cliente' => '9', 'valor' => '5,00'],
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Task::firstOrFail()->users->isEmpty());
    }
}
