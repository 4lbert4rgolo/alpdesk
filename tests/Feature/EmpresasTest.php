<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Store;
use App\Models\User;
use App\Rules\Cnpj;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Lojas" viraram "Empresas": cada empresa tem nome fantasia, razão social, CNPJ,
 * endereço e Encarregado, usados no termo LGPD de quem trabalha nela.
 */
class EmpresasTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $grupo = 'administrador', ?Store $empresa = null): User
    {
        $usuario = User::factory()->create();
        $usuario->accessGroups()->attach(AccessGroup::where('slug', $grupo)->firstOrFail()->id);
        $usuario->forceFill(['store_id' => $empresa?->id, 'two_factor_confirmed_at' => now()])->save();

        return $usuario;
    }

    public function test_tela_de_empresas_abre_pelo_endereco_novo_e_pelo_antigo(): void
    {
        $admin = $this->usuario();

        $this->actingAs($admin)->get('/admin/cadastros/empresas')->assertOk()->assertSee('Nova empresa')->assertSee('Nome fantasia');
        $this->actingAs($admin)->get('/admin/cadastros/lojas')->assertOk()->assertSee('Nova empresa');
    }

    public function test_tela_de_dados_da_empresa(): void
    {
        $empresa = Store::create(['name' => 'Moto Sul (teste)', 'razao_social' => 'Moto Sul Ltda']);
        $admin = $this->usuario();

        $this->actingAs($admin)->get('/admin/cadastros/empresas')
            ->assertSee('Editar dados')
            ->assertSee('Faltam dados para o termo LGPD');

        $this->actingAs($admin)->get("/admin/cadastros/empresas/{$empresa->id}/editar")
            ->assertOk()
            ->assertSee('Dados da empresa')
            ->assertSee('Moto Sul Ltda')
            ->assertSee('Encarregado de dados (LGPD)');

        // Setores não têm essa tela
        $setor = \App\Models\Sector::firstOrCreate(['name' => 'Setor (teste)']);
        $this->actingAs($admin)->get("/admin/cadastros/setores/{$setor->id}/editar")->assertNotFound();
    }

    public function test_salva_os_dados_da_empresa_com_cnpj_formatado(): void
    {
        $empresa = Store::create(['name' => 'Moto Centro (teste)']);

        $this->actingAs($this->usuario())->put("/admin/cadastros/empresas/{$empresa->id}", [
            'name' => 'Moto Centro (teste)',
            'razao_social' => 'Moto Centro Comércio Ltda',
            'cnpj' => '11222333000181',
            'endereco' => 'Rua Um, 100, Centro, Cidade/BA',
            'encarregado_nome' => 'Maria Souza',
            'encarregado_email' => 'privacidade@motocentro.com.br',
        ])->assertSessionHasNoErrors();

        $empresa->refresh();

        $this->assertSame('11.222.333/0001-81', $empresa->cnpj);
        $this->assertSame('Moto Centro Comércio Ltda', $empresa->razao_social);
        $this->assertTrue($empresa->dadosLgpdCompletos());
    }

    public function test_cnpj_invalido_e_recusado(): void
    {
        $empresa = Store::create(['name' => 'Empresa X (teste)']);

        $this->actingAs($this->usuario())->put("/admin/cadastros/empresas/{$empresa->id}", [
            'name' => 'Empresa X (teste)',
            'cnpj' => '11.222.333/0001-82',
        ])->assertSessionHasErrors('cnpj');

        $this->assertNull($empresa->fresh()->cnpj);
    }

    public function test_cnpj_repetido_e_recusado(): void
    {
        Store::create(['name' => 'Empresa A (teste)', 'cnpj' => '11.222.333/0001-81']);
        $outra = Store::create(['name' => 'Empresa B (teste)']);

        $this->actingAs($this->usuario())->put("/admin/cadastros/empresas/{$outra->id}", [
            'name' => 'Empresa B (teste)',
            'cnpj' => '11222333000181', // o mesmo CNPJ, só que sem pontos
        ])->assertSessionHasErrors('cnpj');
    }

    public function test_termo_mostra_os_dados_da_empresa_da_pessoa(): void
    {
        config(['empresa.nome' => 'Padrão do Env Ltda']);

        $empresa = Store::create([
            'name' => 'Moto Norte (teste)',
            'razao_social' => 'Moto Norte Veículos Ltda',
            'cnpj' => '11.222.333/0001-81',
            'endereco' => 'Av. Dois, 200',
            'encarregado_nome' => 'João Lima',
            'encarregado_email' => 'dpo@motonorte.com.br',
        ]);

        $pessoa = $this->usuario('solicitante', $empresa);

        $this->actingAs($pessoa)->get(route('terms.show'))
            ->assertOk()
            ->assertSee('Moto Norte (teste)')
            ->assertSee('Moto Norte Veículos Ltda')
            ->assertSee('11.222.333/0001-81')
            ->assertSee('dpo@motonorte.com.br')
            ->assertDontSee('Padrão do Env Ltda');

        $this->actingAs($pessoa)->get(route('policy.show'))
            ->assertSee('Av. Dois, 200')
            ->assertSee('João Lima');
    }

    public function test_sem_empresa_usa_o_padrao_do_env(): void
    {
        config(['empresa.nome' => 'Padrão do Env Ltda']);

        $this->actingAs($this->usuario('solicitante'))->get(route('terms.show'))->assertSee('Padrão do Env Ltda');
    }

    public function test_campo_vazio_na_empresa_mostra_aviso(): void
    {
        config(['empresa.cnpj' => null]);

        $empresa = Store::create(['name' => 'Incompleta (teste)', 'razao_social' => 'Incompleta Ltda']);

        $this->actingAs($this->usuario('solicitante', $empresa))->get(route('terms.show'))
            ->assertSee('Incompleta Ltda')
            ->assertSee('[preencha o CNPJ no cadastro da empresa]');
    }

    public function test_calculo_do_cnpj(): void
    {
        $this->assertTrue(Cnpj::valido('11.222.333/0001-81'));
        $this->assertFalse(Cnpj::valido('11.222.333/0001-82'));
        $this->assertFalse(Cnpj::valido('00.000.000/0000-00'));
        $this->assertSame('11.222.333/0001-81', Cnpj::formatar('11222333000181'));
    }

    // ------------------------------------------------------------- Locais (ex.: Escritório)

    public function test_escritorio_vira_local_e_nao_pede_dados_de_empresa(): void
    {
        $matriz = Store::create(['name' => 'Matriz (teste)', 'razao_social' => 'Matriz Ltda', 'cnpj' => '11.222.333/0001-81']);
        $escritorio = Store::create(['name' => 'Escritório (teste)']);
        $admin = $this->usuario();

        $this->actingAs($admin)->put("/admin/cadastros/empresas/{$escritorio->id}", [
            'name' => 'Escritório (teste)',
            'tipo' => 'local',
            'empresa_responsavel_id' => $matriz->id,
            'cnpj' => '11.222.333/0001-81', // ignorado: local não tem CNPJ (e não conta como repetido)
        ])->assertSessionHasNoErrors();

        $escritorio->refresh();

        $this->assertTrue($escritorio->ehLocal());
        $this->assertSame($matriz->id, $escritorio->empresa_responsavel_id);
        $this->assertNull($escritorio->cnpj);

        $this->actingAs($admin)->get('/admin/cadastros/empresas')
            ->assertSee('Local (sem CNPJ)')
            ->assertSee('termo LGPD usa Matriz (teste)');

        // A tela do local mostra a escolha da empresa responsável
        $this->actingAs($admin)->get("/admin/cadastros/empresas/{$escritorio->id}/editar")
            ->assertSee('Dados do local')
            ->assertSee('Empresa responsável');
    }

    public function test_quem_trabalha_no_escritorio_ve_o_termo_da_empresa_responsavel(): void
    {
        $matriz = Store::create([
            'name' => 'Matriz (teste)',
            'razao_social' => 'Matriz Veículos Ltda',
            'cnpj' => '11.222.333/0001-81',
            'encarregado_email' => 'dpo@matriz.com.br',
        ]);
        $escritorio = Store::create(['name' => 'Escritório (teste)', 'tipo' => 'local', 'empresa_responsavel_id' => $matriz->id]);

        $this->actingAs($this->usuario('solicitante', $escritorio))->get(route('terms.show'))
            ->assertSee('Matriz Veículos Ltda')
            ->assertSee('11.222.333/0001-81')
            ->assertSee('dpo@matriz.com.br')
            ->assertDontSee('Escritório (teste)');
    }

    public function test_local_sem_responsavel_usa_o_padrao_do_env(): void
    {
        config(['empresa.nome' => 'Padrão do Env Ltda']);

        $escritorio = Store::create(['name' => 'Escritório (teste)', 'tipo' => 'local']);

        $this->actingAs($this->usuario('solicitante', $escritorio))->get(route('terms.show'))
            ->assertSee('Padrão do Env Ltda');
    }

    public function test_local_nao_pode_ser_responsavel(): void
    {
        $escritorio = Store::create(['name' => 'Escritório (teste)', 'tipo' => 'local']);
        $deposito = Store::create(['name' => 'Depósito (teste)']);

        $this->actingAs($this->usuario())->put("/admin/cadastros/empresas/{$deposito->id}", [
            'name' => 'Depósito (teste)',
            'tipo' => 'local',
            'empresa_responsavel_id' => $escritorio->id,
        ])->assertSessionHasErrors('empresa_responsavel_id');
    }

    public function test_equipamento_continua_podendo_ficar_no_escritorio(): void
    {
        $escritorio = Store::create(['name' => 'Escritório (teste)', 'tipo' => 'local']);

        $equipamento = \App\Models\Equipment::create([
            'name' => 'Impressora do escritório',
            'type' => 'Impressora',
            'store_id' => $escritorio->id,
        ]);

        $this->assertSame('Escritório (teste)', $equipamento->store->name);
    }
}
