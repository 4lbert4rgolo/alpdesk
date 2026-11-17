<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Equipment;
use App\Models\EquipmentMovement;
use App\Models\Store;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Patrimônio: alteração com histórico, exclusão com lixeira e comentários.
 */
class EquipamentosPatrimonioTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $grupo): User
    {
        $usuario = User::factory()->create();
        $usuario->accessGroups()->attach(AccessGroup::where('slug', $grupo)->firstOrFail()->id);

        return $usuario;
    }

    private function equipamento(array $extra = []): Equipment
    {
        return Equipment::create(array_merge([
            'name' => 'Notebook Vendas',
            'type' => 'Notebook',
            'status' => 'em_uso',
            'asset_tag' => 'PAT-001',
        ], $extra));
    }

    private function dados(Equipment $e, array $extra = []): array
    {
        return array_merge([
            'name' => $e->name,
            'type' => $e->type,
            'status' => $e->status,
            'asset_tag' => $e->asset_tag,
        ], $extra);
    }

    // ---------------------------------------------------------------- Alteração

    public function test_alteracao_registra_campos_e_motivo_no_historico(): void
    {
        $tecnico = $this->usuario('tecnico');
        $e = $this->equipamento();

        $this->actingAs($tecnico)->put('/equipamentos/'.$e->id, $this->dados($e, [
            'name' => 'Notebook Gerência',
            'hostname' => 'NB-GER-01',
            'motivo_alteracao' => 'Passou para a gerência',
        ]))->assertRedirect('/equipamentos/'.$e->id);

        $mov = EquipmentMovement::where('equipment_id', $e->id)->where('tipo', 'alteracao')->firstOrFail();
        $this->assertSame(['Notebook Vendas', 'Notebook Gerência'], $mov->alteracoes['Nome']);
        $this->assertSame(['—', 'NB-GER-01'], $mov->alteracoes['Nome na rede']);
        $this->assertSame('Passou para a gerência', $mov->comentario);
    }

    public function test_salvar_sem_mudancas_nao_cria_historico(): void
    {
        $tecnico = $this->usuario('tecnico');
        $e = $this->equipamento();

        $this->actingAs($tecnico)->put('/equipamentos/'.$e->id, $this->dados($e))->assertSessionHas('msg', 'Nada foi alterado.');
        $this->assertDatabaseMissing('equipment_movements', ['equipment_id' => $e->id, 'tipo' => 'alteracao']);
    }

    // ----------------------------------------------------------------- Exclusão

    public function test_administrador_exclui_com_motivo_e_restaura(): void
    {
        $admin = $this->usuario('administrador');
        $e = $this->equipamento();

        // Sem motivo não exclui
        $this->actingAs($admin)->delete('/equipamentos/'.$e->id)->assertSessionHasErrors('motivo');
        $this->assertNotSoftDeleted($e);

        $this->actingAs($admin)->delete('/equipamentos/'.$e->id, ['motivo' => 'Cadastro duplicado'])
            ->assertRedirect('/equipamentos');

        $this->assertSoftDeleted($e);
        $this->actingAs($admin)->get('/equipamentos')->assertDontSee('Notebook Vendas');
        $this->actingAs($admin)->get('/equipamentos/'.$e->id)->assertNotFound();
        $this->actingAs($admin)->get('/equipamentos/lixeira')->assertOk()->assertSee('Notebook Vendas')->assertSee('Cadastro duplicado');

        $this->actingAs($admin)->post('/equipamentos/'.$e->id.'/restaurar')->assertRedirect('/equipamentos/'.$e->id);

        $this->assertNotSoftDeleted($e);
        $this->assertDatabaseHas('equipment_movements', ['equipment_id' => $e->id, 'tipo' => 'restauracao']);
    }

    public function test_tecnico_nao_exclui_nem_ve_a_lixeira(): void
    {
        $tecnico = $this->usuario('tecnico');
        $e = $this->equipamento();

        $this->actingAs($tecnico)->delete('/equipamentos/'.$e->id, ['motivo' => 'x'])->assertForbidden();
        $this->actingAs($tecnico)->get('/equipamentos/lixeira')->assertForbidden();
        $this->assertNotSoftDeleted($e);
    }

    public function test_tarefa_continua_mostrando_equipamento_excluido(): void
    {
        $admin = $this->usuario('administrador');
        $e = $this->equipamento();
        $tarefa = Task::create([
            'title' => 'Notebook sem bateria',
            'description' => 'Não carrega',
            'deadline' => now()->addDay()->toDateString(),
            'priority' => 2,
            'user_id' => $admin->id,
            'equipment_id' => $e->id,
        ]);

        $e->delete();

        $this->actingAs($admin)->get('/tasks/'.$tarefa->id)
            ->assertOk()
            ->assertSee('Notebook Vendas')
            ->assertSee('excluído');
    }

    public function test_patrimonio_de_equipamento_na_lixeira_nao_pode_ser_repetido(): void
    {
        $tecnico = $this->usuario('tecnico');
        $this->equipamento()->delete();

        $this->actingAs($tecnico)->post('/equipamentos', [
            'name' => 'Outro notebook',
            'type' => 'Notebook',
            'status' => 'em_uso',
            'asset_tag' => 'PAT-001',
        ])->assertSessionHasErrors('asset_tag');
    }

    // -------------------------------------------------------------- Comentários

    public function test_comentarios_e_quem_pode_apagar(): void
    {
        $autor = $this->usuario('tecnico');
        $colega = $this->usuario('tecnico');
        $admin = $this->usuario('administrador');
        $e = $this->equipamento(['store_id' => Store::create(['name' => 'Matriz'])->id]);

        $this->actingAs($autor)->post('/equipamentos/'.$e->id.'/comentarios', ['comentario' => 'Trocado o HD por SSD'])
            ->assertRedirect();

        $comentario = EquipmentMovement::where('tipo', 'comentario')->firstOrFail();
        $this->actingAs($colega)->get('/equipamentos/'.$e->id)->assertSee('Trocado o HD por SSD');

        // O colega não apaga o comentário de outra pessoa
        $this->actingAs($colega)->delete('/equipamentos/comentarios/'.$comentario->id)->assertForbidden();

        // O administrador pode apagar
        $this->actingAs($admin)->delete('/equipamentos/comentarios/'.$comentario->id)->assertRedirect();
        $this->assertDatabaseMissing('equipment_movements', ['id' => $comentario->id]);
    }

    public function test_autor_apaga_o_proprio_comentario_mas_nao_alteracoes(): void
    {
        $tecnico = $this->usuario('tecnico');
        $e = $this->equipamento();

        $this->actingAs($tecnico)->post('/equipamentos/'.$e->id.'/comentarios', ['comentario' => 'Teste']);
        $comentario = EquipmentMovement::where('tipo', 'comentario')->firstOrFail();
        $this->actingAs($tecnico)->delete('/equipamentos/comentarios/'.$comentario->id)->assertRedirect();

        // Registros automáticos (cadastro, alteração) não podem ser apagados
        $registro = EquipmentMovement::create(['equipment_id' => $e->id, 'user_id' => $tecnico->id, 'tipo' => 'alteracao', 'descricao' => 'Alterou nome']);
        $this->actingAs($tecnico)->delete('/equipamentos/comentarios/'.$registro->id)->assertForbidden();
    }

    public function test_solicitante_nao_comenta_no_inventario(): void
    {
        $solicitante = $this->usuario('solicitante');
        $e = $this->equipamento();

        $this->actingAs($solicitante)->post('/equipamentos/'.$e->id.'/comentarios', ['comentario' => 'Oi'])->assertForbidden();
    }
}
