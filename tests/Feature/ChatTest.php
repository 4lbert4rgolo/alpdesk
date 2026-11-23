<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Chat interno: conversas individuais, grupos, mensagens novas, não lidas e anexos.
 */
class ChatTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $grupo = 'solicitante', array $extra = []): User
    {
        $usuario = User::factory()->create($extra);
        $usuario->accessGroups()->attach(AccessGroup::where('slug', $grupo)->firstOrFail()->id);

        return $usuario;
    }

    private function cabecalhosJson(): array
    {
        return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
    }

    public function test_menu_mostra_o_chat_e_a_tela_abre(): void
    {
        $this->actingAs($this->usuario())->get('/')->assertSee('href="/chat"', false);
        $this->actingAs($this->usuario())->get('/chat')->assertOk()->assertSee('Nova conversa');
    }

    public function test_abre_conversa_individual_sem_duplicar(): void
    {
        $ana = $this->usuario();
        $bruno = $this->usuario();

        $this->actingAs($ana)->post('/chat/individual', ['user_id' => $bruno->id])->assertRedirect();
        $this->actingAs($bruno)->post('/chat/individual', ['user_id' => $ana->id])->assertRedirect();

        $this->assertSame(1, Conversation::where('tipo', 'individual')->count());
    }

    public function test_nao_conversa_consigo_nem_com_conta_desativada(): void
    {
        $ana = $this->usuario();
        $inativo = $this->usuario();
        $inativo->forceFill(['ativo' => false])->save();

        $this->actingAs($ana)->post('/chat/individual', ['user_id' => $ana->id])->assertSessionHasErrors('user_id');
        $this->actingAs($ana)->post('/chat/individual', ['user_id' => $inativo->id])->assertSessionHasErrors('user_id');
    }

    public function test_envia_mensagem_e_o_outro_recebe_como_nao_lida(): void
    {
        $ana = $this->usuario();
        $bruno = $this->usuario();
        $conversa = Conversation::individualEntre($ana, $bruno);

        $this->actingAs($ana)->withHeaders($this->cabecalhosJson())
            ->post('/chat/'.$conversa->id.'/mensagens', ['corpo' => 'Olá! Veja a tarefa #12'])
            ->assertOk()
            ->assertJsonStructure(['id', 'html']);

        $this->assertSame(0, Conversation::naoLidasDe($ana), 'Quem enviou não tem não lidas');
        $this->assertSame(1, Conversation::naoLidasDe($bruno));

        $this->actingAs($bruno)->getJson('/chat/nao-lidas')->assertJson(['total' => 1]);

        // Ao abrir a conversa, a mensagem aparece (com o link da tarefa) e deixa de ser não lida
        $this->actingAs($bruno)->get('/chat/'.$conversa->id)
            ->assertOk()
            ->assertSee('Olá! Veja a tarefa', false)
            ->assertSee('<a href="/tasks/12">#12</a>', false);

        $this->assertSame(0, Conversation::naoLidasDe($bruno));
    }

    public function test_busca_so_as_mensagens_novas(): void
    {
        $ana = $this->usuario();
        $bruno = $this->usuario();
        $conversa = Conversation::individualEntre($ana, $bruno);
        $primeira = Message::create(['conversation_id' => $conversa->id, 'user_id' => $ana->id, 'corpo' => 'primeira']);
        Message::create(['conversation_id' => $conversa->id, 'user_id' => $ana->id, 'corpo' => 'segunda']);

        $resposta = $this->actingAs($bruno)->getJson('/chat/'.$conversa->id.'/mensagens?depois='.$primeira->id)->assertOk();

        $this->assertCount(1, $resposta->json('mensagens'));
        $this->assertStringContainsString('segunda', $resposta->json('mensagens.0.html'));
    }

    public function test_texto_da_mensagem_nao_vira_html(): void
    {
        $ana = $this->usuario();
        $bruno = $this->usuario();
        $conversa = Conversation::individualEntre($ana, $bruno);

        $this->actingAs($ana)->withHeaders($this->cabecalhosJson())
            ->post('/chat/'.$conversa->id.'/mensagens', ['corpo' => '<script>alert(1)</script>'])
            ->assertOk();

        $this->actingAs($bruno)->get('/chat/'.$conversa->id)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_quem_nao_esta_na_conversa_nao_ve_nem_envia(): void
    {
        $ana = $this->usuario();
        $bruno = $this->usuario();
        $curioso = $this->usuario();
        $conversa = Conversation::individualEntre($ana, $bruno);

        $this->actingAs($curioso)->get('/chat/'.$conversa->id)->assertNotFound();
        $this->actingAs($curioso)->getJson('/chat/'.$conversa->id.'/mensagens')->assertNotFound();
        $this->actingAs($curioso)->withHeaders($this->cabecalhosJson())
            ->post('/chat/'.$conversa->id.'/mensagens', ['corpo' => 'oi'])->assertNotFound();
    }

    public function test_mensagem_vazia_e_recusada(): void
    {
        $ana = $this->usuario();
        $conversa = Conversation::individualEntre($ana, $this->usuario());

        $this->actingAs($ana)->withHeaders($this->cabecalhosJson())
            ->post('/chat/'.$conversa->id.'/mensagens', ['corpo' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('corpo');
    }

    public function test_anexo_so_abre_para_quem_esta_na_conversa(): void
    {
        Storage::fake('local');
        $ana = $this->usuario();
        $bruno = $this->usuario();
        $conversa = Conversation::individualEntre($ana, $bruno);

        $this->actingAs($ana)->withHeaders($this->cabecalhosJson())
            ->post('/chat/'.$conversa->id.'/mensagens', ['anexo' => UploadedFile::fake()->create('boleto.pdf', 50, 'application/pdf')])
            ->assertOk();

        $mensagem = Message::firstOrFail();
        Storage::disk('local')->assertExists('chat-anexos/'.$mensagem->anexo_arquivo);

        $this->actingAs($bruno)->get('/chat/'.$conversa->id.'/anexos/'.$mensagem->id)->assertOk();
        $this->actingAs($this->usuario())->get('/chat/'.$conversa->id.'/anexos/'.$mensagem->id)->assertNotFound();
    }

    public function test_so_quem_tem_permissao_cria_grupo(): void
    {
        $membro = $this->usuario();

        $this->actingAs($this->usuario())->post('/chat/grupos', ['nome' => 'Gerentes', 'membros' => [$membro->id]])->assertForbidden();

        $admin = $this->usuario('administrador');
        $this->actingAs($admin)->post('/chat/grupos', ['nome' => 'Gerentes', 'membros' => [$membro->id]])->assertRedirect();

        $grupo = Conversation::where('tipo', 'grupo')->firstOrFail();
        $this->assertSame('Gerentes', $grupo->nome);
        $this->assertTrue($grupo->temParticipante($admin));
        $this->assertTrue($grupo->temParticipante($membro));
        $this->assertTrue($grupo->podeGerenciar($admin));
        $this->assertFalse($grupo->podeGerenciar($membro));
    }

    public function test_grupo_mostra_quem_escreveu_e_membro_pode_sair(): void
    {
        $admin = $this->usuario('administrador');
        $membro = $this->usuario('solicitante', ['name' => 'Carla Vendas']);
        $outro = $this->usuario();
        $this->actingAs($admin)->post('/chat/grupos', ['nome' => 'Loja Centro', 'membros' => [$membro->id, $outro->id]]);
        $grupo = Conversation::where('tipo', 'grupo')->firstOrFail();

        $this->actingAs($membro)->withHeaders($this->cabecalhosJson())->post('/chat/'.$grupo->id.'/mensagens', ['corpo' => 'Bom dia!']);

        $this->actingAs($outro)->get('/chat/'.$grupo->id)->assertSee('Carla Vendas')->assertSee('Bom dia!');

        // Membro comum não muda o grupo, mas pode sair
        $this->actingAs($membro)->put('/chat/'.$grupo->id.'/grupo', ['nome' => 'Outro', 'membros' => [$outro->id]])->assertForbidden();
        $this->actingAs($membro)->post('/chat/'.$grupo->id.'/sair')->assertRedirect('/chat');
        $this->assertFalse($grupo->fresh()->temParticipante($membro));
    }

    public function test_gerente_renomeia_e_tira_membros_sem_se_remover(): void
    {
        $admin = $this->usuario('administrador');
        $fica = $this->usuario();
        $sai = $this->usuario();
        $this->actingAs($admin)->post('/chat/grupos', ['nome' => 'Equipe', 'membros' => [$fica->id, $sai->id]]);
        $grupo = Conversation::where('tipo', 'grupo')->firstOrFail();

        $this->actingAs($admin)->put('/chat/'.$grupo->id.'/grupo', ['nome' => 'Equipe TI', 'membros' => [$fica->id]])->assertRedirect();

        $grupo->refresh();
        $this->assertSame('Equipe TI', $grupo->nome);
        $this->assertTrue($grupo->temParticipante($admin));
        $this->assertTrue($grupo->temParticipante($fica));
        $this->assertFalse($grupo->temParticipante($sai));
    }
}
