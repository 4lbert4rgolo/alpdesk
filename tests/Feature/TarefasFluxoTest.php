<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Equipment;
use App\Models\Store;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AvisoTarefa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Situação das tarefas, linha do tempo, participantes, autorizações,
 * avisos por e-mail, busca e histórico de equipamentos.
 */
class TarefasFluxoTest extends TestCase
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
        return Task::create(array_merge([
            'title' => 'Tarefa de teste',
            'description' => 'Descrição',
            'deadline' => now()->addDays(2)->toDateString(),
            'priority' => 2,
            'items' => [],
            'user_id' => $dono->id,
        ], $extra));
    }

    // ------------------------------------------------------------- Situação

    public function test_assumir_coloca_em_andamento_e_avisa_quem_abriu(): void
    {
        Notification::fake();
        $solicitante = $this->usuario('solicitante');
        $tecnico = $this->usuario('tecnico');
        $tarefa = $this->tarefa($solicitante);

        $this->actingAs($tecnico)->post('/tasks/join/'.$tarefa->id)->assertRedirect('/tasks/'.$tarefa->id);

        $this->assertSame('em_andamento', $tarefa->fresh()->status);
        $this->assertDatabaseHas('task_activities', ['task_id' => $tarefa->id, 'tipo' => 'assumiu', 'user_id' => $tecnico->id]);
        Notification::assertSentTo($solicitante, AvisoTarefa::class);
        Notification::assertNotSentTo($tecnico, AvisoTarefa::class);
    }

    public function test_abrir_tarefa_avisa_os_tecnicos(): void
    {
        Notification::fake();
        $solicitante = $this->usuario('solicitante');
        $tecnico = $this->usuario('tecnico');

        $this->actingAs($solicitante)->post('/tasks', [
            'title' => 'Sem internet',
            'description' => 'Caiu tudo',
            'deadline' => now()->addDay()->toDateString(),
            'priority' => '3',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($tecnico, AvisoTarefa::class);
        Notification::assertNotSentTo($solicitante, AvisoTarefa::class);
    }

    public function test_tecnico_pode_abrir_e_ja_assumir(): void
    {
        $tecnico = $this->usuario('tecnico');

        $this->actingAs($tecnico)->post('/tasks', [
            'title' => 'Trocar toner',
            'description' => 'Impressora da recepção',
            'deadline' => now()->addDay()->toDateString(),
            'priority' => '1',
            'assumir_agora' => '1',
        ])->assertSessionHasNoErrors();

        $tarefa = Task::firstOrFail();
        $this->assertSame('em_andamento', $tarefa->status);
        $this->assertTrue($tarefa->users->contains('id', $tecnico->id));
    }

    public function test_concluir_exige_solucao_e_so_o_responsavel_conclui(): void
    {
        $solicitante = $this->usuario('solicitante');
        $tecnico = $this->usuario('tecnico');
        $outroTecnico = $this->usuario('tecnico');
        $tarefa = $this->tarefa($solicitante, ['status' => 'em_andamento']);
        $tarefa->users()->attach($tecnico->id);

        // Sem descrever a solução
        $this->actingAs($tecnico)->post('/tasks/'.$tarefa->id.'/status', ['acao' => 'concluir'])
            ->assertSessionHasErrors('observacao');

        // Quem abriu e outro técnico não concluem
        $this->actingAs($solicitante)->post('/tasks/'.$tarefa->id.'/status', ['acao' => 'concluir', 'observacao' => 'ok'])
            ->assertForbidden();
        $this->actingAs($outroTecnico)->post('/tasks/'.$tarefa->id.'/status', ['acao' => 'concluir', 'observacao' => 'ok'])
            ->assertForbidden();

        $this->actingAs($tecnico)->post('/tasks/'.$tarefa->id.'/status', ['acao' => 'concluir', 'observacao' => 'Troquei o cabo'])
            ->assertRedirect('/tasks/'.$tarefa->id);

        $tarefa->refresh();
        $this->assertSame('concluida', $tarefa->status);
        $this->assertSame('Troquei o cabo', $tarefa->solucao);
        $this->assertSame($tecnico->id, (int) $tarefa->concluida_por);
        $this->assertNotNull($tarefa->concluida_em);
    }

    public function test_quem_abriu_cancela_e_reabre(): void
    {
        $solicitante = $this->usuario('solicitante');
        $tarefa = $this->tarefa($solicitante);

        $this->actingAs($solicitante)->post('/tasks/'.$tarefa->id.'/status', ['acao' => 'cancelar', 'observacao' => 'Resolvido sozinho']);
        $this->assertSame('cancelada', $tarefa->fresh()->status);

        $this->actingAs($solicitante)->post('/tasks/'.$tarefa->id.'/status', ['acao' => 'reabrir']);
        $tarefa->refresh();
        $this->assertSame('aberta', $tarefa->status);
        $this->assertNull($tarefa->concluida_em);
    }

    public function test_tarefa_concluida_sai_da_lista_padrao(): void
    {
        $tecnico = $this->usuario('tecnico');
        $this->tarefa($tecnico, ['title' => 'Tarefa ainda aberta']);
        $this->tarefa($tecnico, ['title' => 'Tarefa ja resolvida', 'status' => 'concluida']);

        $this->actingAs($tecnico)->get('/tarefas')
            ->assertOk()
            ->assertSee('Tarefa ainda aberta')
            ->assertDontSee('Tarefa ja resolvida');

        $this->actingAs($tecnico)->get('/tarefas?situacao=encerradas')
            ->assertOk()
            ->assertSee('Tarefa ja resolvida')
            ->assertDontSee('Tarefa ainda aberta');
    }

    public function test_busca_encontra_pela_descricao_e_pelo_numero(): void
    {
        $tecnico = $this->usuario('tecnico');
        $tarefa = $this->tarefa($tecnico, ['title' => 'Problema A', 'description' => 'nobreak apitando']);
        $this->tarefa($tecnico, ['title' => 'Problema B']);

        $this->actingAs($tecnico)->get('/tarefas?search=nobreak')->assertSee('Problema A')->assertDontSee('Problema B');
        $this->actingAs($tecnico)->get('/tarefas?search=%23'.$tarefa->id)->assertSee('Problema A');
    }

    public function test_filtro_por_periodo_de_abertura(): void
    {
        $tecnico = $this->usuario('tecnico');
        $antiga = $this->tarefa($tecnico, ['title' => 'Aberta mes passado']);
        $antiga->forceFill(['created_at' => now()->subDays(40)])->save();
        $this->tarefa($tecnico, ['title' => 'Aberta hoje cedo']);

        $de = now()->subDays(6)->toDateString();
        $ate = now()->toDateString();

        $this->actingAs($tecnico)->get("/tarefas?de={$de}&ate={$ate}")
            ->assertOk()
            ->assertSee('Aberta hoje cedo')
            ->assertDontSee('Aberta mes passado');

        // Datas invertidas são corrigidas
        $this->actingAs($tecnico)->get("/tarefas?de={$ate}&ate={$de}")->assertSee('Aberta hoje cedo');

        // Data inválida é ignorada
        $this->actingAs($tecnico)->get('/tarefas?de=abc')->assertOk()->assertSee('Aberta mes passado');
    }

    public function test_filtro_por_responsavel_e_sem_responsavel(): void
    {
        $tecnico = $this->usuario('tecnico');
        $outro = $this->usuario('tecnico');
        $minha = $this->tarefa($tecnico, ['title' => 'Atendida pelo tecnico']);
        $minha->users()->attach($tecnico->id);
        $dele = $this->tarefa($tecnico, ['title' => 'Atendida pelo colega']);
        $dele->users()->attach($outro->id);
        $this->tarefa($tecnico, ['title' => 'Ninguem pegou']);

        $this->actingAs($tecnico)->get('/tarefas?responsavel='.$tecnico->id)
            ->assertSee('Atendida pelo tecnico')
            ->assertDontSee('Atendida pelo colega')
            ->assertDontSee('Ninguem pegou');

        $this->actingAs($tecnico)->get('/tarefas?responsavel=sem')
            ->assertSee('Ninguem pegou')
            ->assertDontSee('Atendida pelo tecnico');
    }

    public function test_filtro_por_loja(): void
    {
        $tecnico = $this->usuario('tecnico');
        $matriz = Store::create(['name' => 'Matriz']);
        $filial = Store::create(['name' => 'Filial']);
        $pcMatriz = Equipment::create(['name' => 'PC 1', 'type' => 'Desktop', 'status' => 'em_uso', 'store_id' => $matriz->id]);
        $pcFilial = Equipment::create(['name' => 'PC 2', 'type' => 'Desktop', 'status' => 'em_uso', 'store_id' => $filial->id]);
        $this->tarefa($tecnico, ['title' => 'Problema na matriz', 'equipment_id' => $pcMatriz->id]);
        $this->tarefa($tecnico, ['title' => 'Problema na filial', 'equipment_id' => $pcFilial->id]);
        $this->tarefa($tecnico, ['title' => 'Problema sem equipamento']);

        $this->actingAs($tecnico)->get('/tarefas?loja='.$matriz->id)
            ->assertSee('Problema na matriz')
            ->assertDontSee('Problema na filial')
            ->assertDontSee('Problema sem equipamento');

        $this->actingAs($tecnico)->get('/tarefas?loja=sem')
            ->assertSee('Problema sem equipamento')
            ->assertDontSee('Problema na matriz');
    }

    public function test_filtros_nao_mostram_tarefas_que_o_solicitante_nao_pode_ver(): void
    {
        $eu = $this->usuario('solicitante');
        $outro = $this->usuario('solicitante');
        $this->tarefa($outro, ['title' => 'Tarefa reservada']);
        $this->tarefa($eu, ['title' => 'Minha tarefa']);

        $this->actingAs($eu)->get('/tarefas?situacao=todas&responsavel=sem')
            ->assertSee('Minha tarefa')
            ->assertDontSee('Tarefa reservada');
    }

    // --------------------------------------------------------- Participantes

    public function test_participante_ve_comenta_e_autoriza_mas_nao_edita(): void
    {
        Notification::fake();
        $dono = $this->usuario('solicitante');
        $gerente = $this->usuario('solicitante');
        $tarefa = $this->tarefa($dono, ['title' => 'Troca do monitor do caixa']);

        // Antes de participar, o gerente (Solicitante) não vê a tarefa
        $this->actingAs($gerente)->get('/tasks/'.$tarefa->id)->assertNotFound();
        $this->actingAs($gerente)->post('/tasks/'.$tarefa->id.'/comentarios', ['corpo' => 'Oi'])->assertNotFound();

        // Quem abriu adiciona o gerente
        $this->actingAs($dono)->post('/tasks/'.$tarefa->id.'/participantes', ['user_id' => $gerente->id])
            ->assertRedirect('/tasks/'.$tarefa->id);
        Notification::assertSentTo($gerente, AvisoTarefa::class);

        // Agora vê, aparece na lista dele e pode autorizar
        $this->actingAs($gerente)->get('/tasks/'.$tarefa->id)->assertOk()->assertSee('Autorizo');
        $this->actingAs($gerente)->get('/tarefas')->assertSee('Troca do monitor do caixa');
        $this->actingAs($gerente)->post('/tasks/'.$tarefa->id.'/comentarios', ['tipo' => 'autorizacao', 'corpo' => 'Pode trocar.'])
            ->assertRedirect();

        $this->assertDatabaseHas('task_activities', ['task_id' => $tarefa->id, 'tipo' => 'autorizacao', 'user_id' => $gerente->id]);
        Notification::assertSentTo($dono, AvisoTarefa::class);

        // Mas não edita, não exclui e não conclui
        $this->actingAs($gerente)->get('/tasks/edit/'.$tarefa->id)->assertRedirect('/dashboard');
        $this->actingAs($gerente)->delete('/tasks/'.$tarefa->id)->assertForbidden();
        $this->actingAs($gerente)->post('/tasks/'.$tarefa->id.'/status', ['acao' => 'concluir', 'observacao' => 'x'])->assertForbidden();
    }

    public function test_quem_nao_e_participante_nao_registra_autorizacao(): void
    {
        $dono = $this->usuario('solicitante');
        $tecnico = $this->usuario('tecnico');
        $tarefa = $this->tarefa($dono);

        // O técnico vê e comenta, mas não autoriza
        $this->actingAs($tecnico)->post('/tasks/'.$tarefa->id.'/comentarios', ['corpo' => 'Vou verificar'])->assertRedirect();
        $this->actingAs($tecnico)->post('/tasks/'.$tarefa->id.'/comentarios', ['tipo' => 'autorizacao', 'corpo' => 'ok'])->assertForbidden();

        // Quem abriu também não autoriza a própria tarefa
        $this->actingAs($dono)->post('/tasks/'.$tarefa->id.'/comentarios', ['tipo' => 'autorizacao', 'corpo' => 'ok'])->assertForbidden();
    }

    public function test_participante_nao_adiciona_outros_mas_pode_sair(): void
    {
        $dono = $this->usuario('solicitante');
        $participante = $this->usuario('solicitante');
        $terceiro = $this->usuario('solicitante');
        $tarefa = $this->tarefa($dono);
        $tarefa->participantes()->attach($participante->id);

        $this->actingAs($participante)->post('/tasks/'.$tarefa->id.'/participantes', ['user_id' => $terceiro->id])
            ->assertForbidden();

        $this->actingAs($participante)->delete('/tasks/'.$tarefa->id.'/participantes/'.$participante->id)
            ->assertRedirect('/dashboard');

        $this->assertDatabaseMissing('task_participants', ['task_id' => $tarefa->id, 'user_id' => $participante->id]);
        $this->actingAs($participante)->get('/tasks/'.$tarefa->id)->assertNotFound();
    }

    public function test_participante_ve_a_imagem_da_tarefa(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $dono = $this->usuario('solicitante');
        $participante = $this->usuario('solicitante');
        $tarefa = $this->tarefa($dono, ['image' => 'foto.jpg']);
        \Illuminate\Support\Facades\Storage::disk('local')->put('task-images/foto.jpg', 'conteudo');

        $this->actingAs($participante)->get('/tasks/'.$tarefa->id.'/imagem')->assertNotFound();

        $tarefa->participantes()->attach($participante->id);

        $this->actingAs($participante)->get('/tasks/'.$tarefa->id.'/imagem')->assertOk();
    }

    public function test_minhas_tarefas_mostra_as_que_participo(): void
    {
        $dono = $this->usuario('solicitante');
        $eu = $this->usuario('solicitante');
        $tarefa = $this->tarefa($dono, ['title' => 'Tarefa que acompanho']);
        $tarefa->participantes()->attach($eu->id);

        $this->actingAs($eu)->get('/dashboard')->assertOk()->assertSee('Tarefa que acompanho')->assertSee('Participante');
    }

    // ---------------------------------------------------------- Equipamentos

    public function test_troca_de_loja_fica_no_historico_do_equipamento(): void
    {
        $tecnico = $this->usuario('tecnico');
        $admin = $this->usuario('administrador');
        $matriz = Store::create(['name' => 'Matriz']);
        $filial = Store::create(['name' => 'Filial']);
        $equipamento = Equipment::create(['name' => 'PC Caixa', 'type' => 'Desktop', 'status' => 'em_uso', 'store_id' => $matriz->id]);

        $this->actingAs($admin)->put('/equipamentos/'.$equipamento->id, [
            'name' => 'PC Caixa',
            'type' => 'Desktop',
            'status' => 'em_uso',
            'store_id' => $filial->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('equipment_movements', ['equipment_id' => $equipamento->id, 'user_id' => $admin->id]);

        $this->actingAs($tecnico)->get('/equipamentos/'.$equipamento->id)
            ->assertOk()
            ->assertSee('Matriz')
            ->assertSee('Filial');

        $this->actingAs($tecnico)->get('/equipamentos/'.$equipamento->id.'/etiqueta')->assertOk()->assertSee('PC Caixa');
    }
}
