<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Equipment;
use App\Models\Sector;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setor e loja do usuário, cadastro de setores/lojas e equipamento ligado à conta de quem usa.
 */
class SetoresLojasTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $grupo, array $extra = []): User
    {
        $usuario = User::factory()->create($extra);
        $usuario->accessGroups()->attach(AccessGroup::where('slug', $grupo)->firstOrFail()->id);

        return $usuario;
    }

    private function equipamento(array $extra = []): Equipment
    {
        return Equipment::create(array_merge([
            'name' => 'Desktop Financeiro',
            'type' => 'Desktop',
            'status' => 'em_uso',
        ], $extra));
    }

    // ------------------------------------------------------ Usuário: setor e loja

    public function test_administrador_cria_usuario_com_setor_e_loja(): void
    {
        $setor = Sector::create(['name' => 'Financeiro']);
        $loja = Store::create(['name' => 'Matriz']);

        $this->actingAs($this->usuario('administrador'))->post('/admin/usuarios', [
            'name' => 'Ana',
            'email' => 'ana@empresa.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
            'sector_id' => $setor->id,
            'store_id' => $loja->id,
        ])->assertSessionHasNoErrors();

        $ana = User::where('email', 'ana@empresa.com')->firstOrFail();
        $this->assertSame($setor->id, $ana->sector_id);
        $this->assertSame($loja->id, $ana->store_id);
    }

    public function test_editar_troca_e_limpa_setor(): void
    {
        $antigo = Sector::create(['name' => 'Vendas']);
        $novo = Sector::create(['name' => 'Oficina']);
        $pessoa = $this->usuario('solicitante');
        $pessoa->forceFill(['sector_id' => $antigo->id])->save();
        $admin = $this->usuario('administrador');

        $dados = ['name' => $pessoa->name, 'email' => $pessoa->email, 'groups' => [AccessGroup::where('slug', 'solicitante')->value('id')]];

        $this->actingAs($admin)->put('/admin/usuarios/'.$pessoa->id, $dados + ['sector_id' => $novo->id])->assertSessionHasNoErrors();
        $this->assertSame($novo->id, $pessoa->fresh()->sector_id);

        $this->actingAs($admin)->put('/admin/usuarios/'.$pessoa->id, $dados + ['sector_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($pessoa->fresh()->sector_id);
    }

    public function test_pessoa_nao_muda_o_proprio_setor_pelo_perfil(): void
    {
        $setor = Sector::create(['name' => 'Diretoria']);
        $pessoa = $this->usuario('solicitante');

        // sector_id não está em $fillable: tentar preencher em massa não tem efeito
        $pessoa->fill(['sector_id' => $setor->id])->save();

        $this->assertNull($pessoa->fresh()->sector_id);
    }

    public function test_lista_de_usuarios_filtra_por_setor(): void
    {
        $financeiro = Sector::create(['name' => 'Financeiro']);
        $this->usuario('solicitante', ['name' => 'Bruno Financeiro'])->forceFill(['sector_id' => $financeiro->id])->save();
        $this->usuario('solicitante', ['name' => 'Carla Oficina']);

        $this->actingAs($this->usuario('administrador'))
            ->get('/admin/usuarios?setor='.$financeiro->id)
            ->assertOk()
            ->assertSee('Bruno Financeiro')
            ->assertDontSee('Carla Oficina');
    }

    // --------------------------------------------------- Cadastro de setores/lojas

    public function test_so_quem_tem_permissao_abre_setores_e_lojas(): void
    {
        $this->actingAs($this->usuario('tecnico'))->get('/admin/cadastros/setores')->assertForbidden();
        $this->actingAs($this->usuario('administrador'))->get('/admin/cadastros/setores')->assertOk();
        $this->actingAs($this->usuario('administrador'))->get('/admin/cadastros/lojas')->assertOk();
        $this->actingAs($this->usuario('administrador'))->get('/admin/cadastros/outra-coisa')->assertNotFound();
    }

    public function test_cria_renomeia_e_nao_repete_nome(): void
    {
        $admin = $this->usuario('administrador');

        $this->actingAs($admin)->post('/admin/cadastros/setores', ['name' => 'Peças'])->assertSessionHasNoErrors();
        $setor = Sector::where('name', 'Peças')->firstOrFail();

        $this->actingAs($admin)->post('/admin/cadastros/setores', ['name' => 'Peças'])->assertSessionHasErrors('name');

        $this->actingAs($admin)->put('/admin/cadastros/setores/'.$setor->id, ['name' => 'Peças e Acessórios'])->assertSessionHasNoErrors();
        $this->assertSame('Peças e Acessórios', $setor->fresh()->name);
    }

    public function test_nao_exclui_setor_em_uso(): void
    {
        $admin = $this->usuario('administrador');
        $usado = Sector::create(['name' => 'Vendas']);
        $livre = Sector::create(['name' => 'Antigo']);
        $this->usuario('solicitante')->forceFill(['sector_id' => $usado->id])->save();

        $this->actingAs($admin)->delete('/admin/cadastros/setores/'.$usado->id)->assertSessionHasErrors('name');
        $this->assertNotNull($usado->fresh());

        $this->actingAs($admin)->delete('/admin/cadastros/setores/'.$livre->id)->assertSessionHasNoErrors();
        $this->assertNull($livre->fresh());
    }

    // ------------------------------------------------ Equipamento ligado à conta

    public function test_vincula_equipamento_a_conta_e_registra_no_historico(): void
    {
        $tecnico = $this->usuario('tecnico');
        $pessoa = $this->usuario('solicitante', ['name' => 'Débora']);
        $equipamento = $this->equipamento(['user_name' => 'Nome antigo']);

        $this->actingAs($tecnico)->put('/equipamentos/'.$equipamento->id, [
            'name' => $equipamento->name,
            'type' => $equipamento->type,
            'status' => $equipamento->status,
            'user_id' => $pessoa->id,
            'user_name' => 'Nome antigo',
        ])->assertSessionHasNoErrors();

        $equipamento->refresh();
        $this->assertSame($pessoa->id, $equipamento->user_id);
        $this->assertNull($equipamento->user_name, 'Com conta escolhida, o nome digitado é apagado');
        $this->assertSame('Débora', $equipamento->quemUsa());

        $historico = $equipamento->movimentacoes()->first();
        $this->assertSame(['Nenhum', 'Débora'], $historico->alteracoes['Usuário do sistema']);
    }

    public function test_nao_vincula_conta_desativada(): void
    {
        $tecnico = $this->usuario('tecnico');
        $inativo = $this->usuario('solicitante');
        $inativo->forceFill(['ativo' => false])->save();
        $equipamento = $this->equipamento();

        $this->actingAs($tecnico)->put('/equipamentos/'.$equipamento->id, [
            'name' => $equipamento->name,
            'type' => $equipamento->type,
            'status' => $equipamento->status,
            'user_id' => $inativo->id,
        ])->assertSessionHasErrors('user_id');
    }

    public function test_equipamento_de_quem_foi_desativado_continua_editavel(): void
    {
        $tecnico = $this->usuario('tecnico');
        $pessoa = $this->usuario('solicitante');
        $equipamento = $this->equipamento(['user_id' => $pessoa->id]);
        $pessoa->forceFill(['ativo' => false])->save();

        $this->actingAs($tecnico)->put('/equipamentos/'.$equipamento->id, [
            'name' => 'Desktop Financeiro 2',
            'type' => $equipamento->type,
            'status' => $equipamento->status,
            'user_id' => $pessoa->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Desktop Financeiro 2', $equipamento->fresh()->name);
    }

    public function test_busca_e_filtro_de_equipamentos_pela_pessoa(): void
    {
        $tecnico = $this->usuario('tecnico');
        $pessoa = $this->usuario('solicitante', ['name' => 'Fernanda Caixa']);
        $this->equipamento(['name' => 'Notebook da Fernanda', 'user_id' => $pessoa->id]);
        $this->equipamento(['name' => 'Impressora Oficina']);

        $this->actingAs($tecnico)->get('/equipamentos?q=Fernanda Caixa')
            ->assertOk()->assertSee('Notebook da Fernanda')->assertDontSee('Impressora Oficina');

        $this->actingAs($tecnico)->get('/equipamentos?usuario='.$pessoa->id)
            ->assertOk()->assertSee('Notebook da Fernanda')->assertDontSee('Impressora Oficina');
    }

    public function test_ficha_do_usuario_mostra_os_equipamentos_dele(): void
    {
        $pessoa = $this->usuario('solicitante');
        $this->equipamento(['name' => 'Monitor LG 24', 'user_id' => $pessoa->id]);

        $this->actingAs($this->usuario('administrador'))
            ->get('/admin/usuarios/'.$pessoa->id.'/editar')
            ->assertOk()
            ->assertSee('Monitor LG 24');
    }
}
