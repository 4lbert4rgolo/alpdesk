<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Painel de usuários: edição, ativar/desativar, travas de segurança e ações de conta.
 */
class AdminUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $grupo, array $extra = []): User
    {
        $usuario = User::factory()->create($extra);
        $usuario->accessGroups()->attach(AccessGroup::where('slug', $grupo)->firstOrFail()->id);

        // Técnicos atendem o setor TI (criado pela migration), como no sistema real
        if ($grupo === 'tecnico') {
            $usuario->forceFill(['sector_id' => \App\Models\Sector::where('name', 'TI')->value('id')])->save();
        }

        return $usuario;
    }

    private function grupoId(string $slug): int
    {
        return AccessGroup::where('slug', $slug)->firstOrFail()->id;
    }

    public function test_so_quem_gerencia_usuarios_acessa_o_painel(): void
    {
        $this->actingAs($this->usuario('tecnico'))->get('/admin/usuarios')->assertForbidden();
        $this->actingAs($this->usuario('administrador'))->get('/admin/usuarios')->assertOk();
    }

    public function test_administrador_edita_nome_email_e_grupos(): void
    {
        $admin = $this->usuario('administrador');
        $pessoa = $this->usuario('solicitante', ['name' => 'Maria', 'email' => 'maria@empresa.com']);

        $this->actingAs($admin)->put('/admin/usuarios/'.$pessoa->id, [
            'name' => 'Maria Souza',
            'email' => 'Maria.Souza@Empresa.com',
            'groups' => [$this->grupoId('tecnico')],
        ])->assertRedirect('/admin/usuarios/'.$pessoa->id.'/editar');

        $pessoa->refresh();
        $this->assertSame('Maria Souza', $pessoa->name);
        $this->assertSame('maria.souza@empresa.com', $pessoa->email);
        $this->assertTrue($pessoa->can('tarefas.assumir'));
        $this->assertFalse($pessoa->accessGroups->contains('slug', 'solicitante'));
    }

    public function test_busca_e_filtro_por_situacao(): void
    {
        $admin = $this->usuario('administrador');
        // E-mails fixos: o e-mail aleatório da factory às vezes contém "joana" e atrapalhava a busca
        $this->usuario('solicitante', ['name' => 'Joana Vendas', 'email' => 'joana.vendas@empresa.com']);
        $inativo = $this->usuario('solicitante', ['name' => 'Pedro Antigo', 'email' => 'pedro.antigo@empresa.com']);
        $inativo->forceFill(['ativo' => false])->save();

        $this->actingAs($admin)->get('/admin/usuarios?q=joana')->assertSee('Joana Vendas')->assertDontSee('Pedro Antigo');
        $this->actingAs($admin)->get('/admin/usuarios?situacao=desativados')->assertSee('Pedro Antigo')->assertDontSee('Joana Vendas');
    }

    // ------------------------------------------------------- Ativar / desativar

    public function test_administrador_desativa_e_reativa_conta(): void
    {
        $admin = $this->usuario('administrador');
        $pessoa = $this->usuario('solicitante');

        $this->actingAs($admin)->post('/admin/usuarios/'.$pessoa->id.'/desativar')->assertSessionHas('msg');
        $this->assertFalse($pessoa->fresh()->ativo);

        $this->actingAs($admin)->post('/admin/usuarios/'.$pessoa->id.'/ativar')->assertSessionHas('msg');
        $this->assertTrue($pessoa->fresh()->ativo);
    }

    public function test_conta_desativada_nao_consegue_entrar(): void
    {
        $pessoa = $this->usuario('solicitante', ['email' => 'joao@empresa.com']);
        $pessoa->forceFill(['ativo' => false])->save();

        $this->post('/login', ['email' => 'joao@empresa.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_conta_ativa_entra_com_email_em_maiusculas_e_registra_ultimo_acesso(): void
    {
        $pessoa = $this->usuario('solicitante', ['email' => 'joao@empresa.com']);

        $this->post('/login', ['email' => 'JOAO@empresa.com', 'password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($pessoa);
        $this->assertNotNull($pessoa->fresh()->ultimo_acesso_em);
    }

    public function test_senha_errada_continua_com_a_mensagem_padrao(): void
    {
        $pessoa = $this->usuario('solicitante', ['email' => 'joao@empresa.com']);
        $pessoa->forceFill(['ativo' => false])->save();

        // Com a senha errada não revela que a conta existe e está desativada
        $this->post('/login', ['email' => 'joao@empresa.com', 'password' => 'errada'])
            ->assertSessionHasErrors('email');

        $this->assertStringNotContainsString('desativada', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_quem_estava_logado_e_desconectado_ao_ser_desativado(): void
    {
        $pessoa = $this->usuario('tecnico');
        $pessoa->forceFill(['ativo' => false])->save();

        $this->actingAs($pessoa)->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_ninguem_desativa_a_si_mesmo(): void
    {
        $admin = $this->usuario('administrador');
        $this->usuario('administrador'); // existe outro administrador

        $this->actingAs($admin)->post('/admin/usuarios/'.$admin->id.'/desativar')->assertSessionHasErrors('conta');
        $this->assertTrue($admin->fresh()->ativo);
    }

    public function test_nao_deixa_o_sistema_sem_administrador_ativo(): void
    {
        $admin = $this->usuario('administrador');
        $gestor = $this->usuario('tecnico');
        // Dá ao técnico a permissão de gerenciar usuários, sem ser administrador
        AccessGroup::where('slug', 'tecnico')->first()->update(['permissions' => ['tarefas.criar', 'tarefas.assumir', 'usuarios.gerenciar']]);

        $this->actingAs($gestor)->post('/admin/usuarios/'.$admin->id.'/desativar')->assertSessionHasErrors('conta');
        $this->assertTrue($admin->fresh()->ativo);

        // Quem não é administrador não altera a conta de um administrador
        $this->actingAs($gestor)->put('/admin/usuarios/'.$admin->id, [
            'name' => $admin->name,
            'email' => $admin->email,
            'groups' => [$this->grupoId('tecnico')],
        ])->assertSessionHasErrors('conta');
        $this->assertTrue($admin->fresh()->isAdministrator());
    }

    public function test_unico_administrador_nao_tira_o_proprio_grupo(): void
    {
        $admin = $this->usuario('administrador');

        $this->actingAs($admin)->put('/admin/usuarios/'.$admin->id, [
            'name' => $admin->name,
            'email' => $admin->email,
            'groups' => [$this->grupoId('tecnico')],
        ])->assertSessionHasErrors('groups');
    }

    // ------------------------------------------------- Escalada de privilégios

    private function gestorDeUsuarios(): User
    {
        // Grupo "Gestor": gerencia usuários e grupos, mas não é administrador
        $grupo = AccessGroup::create([
            'name' => 'Gestor',
            'slug' => 'gestor',
            'permissions' => ['usuarios.gerenciar', 'grupos.gerenciar', 'tarefas.criar'],
        ]);
        $gestor = User::factory()->create();
        $gestor->accessGroups()->attach($grupo->id);

        return $gestor;
    }

    public function test_gestor_nao_se_promove_a_administrador(): void
    {
        $this->usuario('administrador');
        $gestor = $this->gestorDeUsuarios();

        $this->actingAs($gestor)->put('/admin/usuarios/'.$gestor->id, [
            'name' => $gestor->name,
            'email' => $gestor->email,
            'groups' => [AccessGroup::where('slug', 'gestor')->value('id'), $this->grupoId('administrador')],
        ])->assertSessionHasErrors('groups');

        $this->assertFalse($gestor->fresh()->isAdministrator());

        // Também não cria um novo administrador
        $this->actingAs($gestor)->post('/admin/usuarios', [
            'name' => 'Comparsa',
            'email' => 'comparsa@empresa.com',
            'password' => 'SenhaForte123',
            'password_confirmation' => 'SenhaForte123',
            'groups' => [$this->grupoId('administrador')],
        ])->assertSessionHasErrors('groups');

        $this->assertDatabaseMissing('users', ['email' => 'comparsa@empresa.com']);
    }

    public function test_gestor_nao_concede_permissao_que_nao_tem(): void
    {
        $gestor = $this->gestorDeUsuarios();
        $grupoGestor = AccessGroup::where('slug', 'gestor')->first();

        // Tentar dar a si mesmo a permissão de excluir equipamentos
        $this->actingAs($gestor)->put('/admin/grupos/'.$grupoGestor->id, [
            'name' => 'Gestor',
            'permissions' => ['usuarios.gerenciar', 'grupos.gerenciar', 'tarefas.criar', 'equipamentos.excluir'],
        ])->assertSessionHasErrors('permissions');

        $this->assertNotContains('equipamentos.excluir', $grupoGestor->fresh()->permissions);

        // Nem alterar um grupo que tem permissões que ele não tem (Técnico pode assumir tarefas)
        $tecnico = AccessGroup::where('slug', 'tecnico')->first();
        $this->actingAs($gestor)->put('/admin/grupos/'.$tecnico->id, [
            'name' => 'Técnico',
            'permissions' => [],
        ])->assertSessionHasErrors('permissions');
    }

    // ------------------------------------------- 2FA obrigatória para administradores

    public function test_administrador_sem_2fa_e_levado_ao_perfil_quando_obrigatorio(): void
    {
        config(['alpdesk.exigir_2fa_admin' => true]);
        $admin = $this->usuario('administrador');
        $tecnico = $this->usuario('tecnico');

        $this->actingAs($admin)->get('/')->assertRedirect('/user/profile');
        $this->actingAs($admin)->get('/user/profile')->assertOk();

        // Quem não é administrador não é afetado
        $this->actingAs($tecnico)->get('/')->assertOk();

        // Com a verificação em duas etapas ativada, o administrador usa normalmente
        $admin->forceFill([
            'two_factor_secret' => encrypt('segredo'),
            'two_factor_recovery_codes' => encrypt('[]'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($admin->fresh())->get('/')->assertOk();
    }

    public function test_esqueci_a_senha_nao_revela_se_o_email_existe(): void
    {
        app()->setLocale('pt_BR');

        // E-mail que não existe recebe a MESMA mensagem de quando existe
        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'ninguem@empresa.com'])
            ->assertSessionHasErrors(['email' => 'Se o e-mail estiver cadastrado, você receberá o link para redefinir a senha.']);
    }

    // ----------------------------------------------------------- Ações de conta

    public function test_envia_link_para_redefinir_senha(): void
    {
        Notification::fake();
        $admin = $this->usuario('administrador');
        $pessoa = $this->usuario('solicitante');

        $this->actingAs($admin)->post('/admin/usuarios/'.$pessoa->id.'/redefinir-senha')->assertSessionHas('msg');

        Notification::assertSentTo($pessoa, ResetPassword::class);
    }

    public function test_remove_verificacao_em_duas_etapas(): void
    {
        $admin = $this->usuario('administrador');
        $pessoa = $this->usuario('solicitante');
        $pessoa->forceFill([
            'two_factor_secret' => encrypt('segredo'),
            'two_factor_recovery_codes' => encrypt('[]'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($admin)->get('/admin/usuarios/'.$pessoa->id.'/editar')->assertSee('Remover verificação em duas etapas');
        $this->actingAs($admin)->post('/admin/usuarios/'.$pessoa->id.'/remover-2fa')->assertSessionHas('msg');

        $pessoa->refresh();
        $this->assertNull($pessoa->two_factor_secret);
        $this->assertNull($pessoa->two_factor_confirmed_at);
    }

    public function test_confirma_email_manualmente(): void
    {
        $admin = $this->usuario('administrador');
        $pessoa = $this->usuario('solicitante', ['email_verified_at' => null]);

        $this->actingAs($admin)->post('/admin/usuarios/'.$pessoa->id.'/confirmar-email');

        $this->assertNotNull($pessoa->fresh()->email_verified_at);
    }

    public function test_conta_desativada_nao_aparece_para_virar_participante(): void
    {
        $dono = $this->usuario('tecnico');
        $inativo = $this->usuario('solicitante', ['name' => 'Fulano Desligado']);
        $inativo->forceFill(['ativo' => false])->save();
        $tarefa = \App\Models\Task::create([
            'title' => 'Teste',
            'description' => 'x',
            'deadline' => now()->addDay()->toDateString(),
            'priority' => 2,
            'user_id' => $dono->id,
        ]);

        $this->actingAs($dono)->get('/tasks/'.$tarefa->id)->assertOk()->assertDontSee('Fulano Desligado');
    }
}
