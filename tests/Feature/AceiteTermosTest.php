<?php

namespace Tests\Feature;

use App\Models\TermAcceptance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

/**
 * LGPD: aceite dos Termos de Uso e da Política de Privacidade.
 *
 * No phpunit.xml o aceite vem desligado (TERMS_ACCEPTANCE_REQUIRED=false) para não
 * atrapalhar os outros testes. Aqui ele é ligado em cada teste.
 */
class AceiteTermosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'alpdesk.exigir_aceite_termos' => true,
            'alpdesk.termos_versao' => '2026-11-23',
        ]);
    }

    public function test_quem_nao_aceitou_e_levado_para_a_tela_de_aceite(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/dashboard')->assertRedirect(route('termos.aceite'));

        $this->actingAs($usuario)->get(route('termos.aceite'))
            ->assertOk()
            ->assertSee('Aceitar e continuar');
    }

    public function test_textos_dos_termos_e_da_politica_continuam_abertos(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get(route('terms.show'))->assertOk()->assertSee('Termos de Uso');
        $this->actingAs($usuario)->get(route('policy.show'))->assertOk()->assertSee('Política de Privacidade');
    }

    public function test_aceite_sem_marcar_a_caixa_e_recusado(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post(route('termos.aceitar'), [])
            ->assertSessionHasErrors('aceite');

        $this->assertDatabaseCount('term_acceptances', 0);
    }

    public function test_aceite_e_registrado_e_libera_o_sistema(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->withServerVariables(['REMOTE_ADDR' => '200.10.20.30'])
            ->post(route('termos.aceitar'), ['aceite' => '1'])
            ->assertRedirect('/');

        $this->assertDatabaseHas('term_acceptances', [
            'user_id' => $usuario->id,
            'versao' => '2026-11-23',
            'ip' => '200.10.20.30',
        ]);

        $this->actingAs($usuario)->get('/dashboard')->assertOk();
    }

    public function test_volta_para_a_tela_que_a_pessoa_queria_abrir(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/equipamentos');

        $this->actingAs($usuario)->post(route('termos.aceitar'), ['aceite' => '1'])
            ->assertRedirect(url('/equipamentos'));
    }

    public function test_nova_versao_pede_aceite_de_novo_e_guarda_o_historico(): void
    {
        $usuario = User::factory()->create();
        TermAcceptance::registrar($usuario);

        $this->actingAs($usuario)->get('/dashboard')->assertOk();

        config(['alpdesk.termos_versao' => '2027-03-01']);

        $this->actingAs($usuario)->get('/dashboard')->assertRedirect(route('termos.aceite'));
        $this->actingAs($usuario)->get(route('termos.aceite'))->assertSee('Os termos foram atualizados');

        $this->actingAs($usuario)->post(route('termos.aceitar'), ['aceite' => '1']);

        $this->assertSame(2, $usuario->aceitesTermos()->count());
    }

    public function test_aceitar_duas_vezes_nao_duplica(): void
    {
        $usuario = User::factory()->create();

        TermAcceptance::registrar($usuario);
        TermAcceptance::registrar($usuario);

        $this->assertSame(1, $usuario->aceitesTermos()->count());
    }

    public function test_recusar_desconecta(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post(route('termos.recusar'))->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_pedido_por_javascript_recebe_403(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->getJson('/dashboard')->assertForbidden();
    }

    public function test_quem_se_cadastra_ja_fica_com_o_aceite_registrado(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('O cadastro está desligado.');
        }

        config(['alpdesk.allowed_email_domains' => []]);

        $this->post('/register', [
            'name' => 'Pessoa Teste',
            'email' => 'pessoa@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ]);

        $usuario = User::where('email', 'pessoa@example.com')->first();

        $this->assertNotNull($usuario, 'O cadastro deveria ter criado o usuário.');
        $this->assertTrue($usuario->aceitouTermosAtuais());
    }

    public function test_com_a_exigencia_desligada_ninguem_e_bloqueado(): void
    {
        config(['alpdesk.exigir_aceite_termos' => false]);

        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();
    }
}
