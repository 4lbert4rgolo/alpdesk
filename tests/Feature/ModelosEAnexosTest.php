<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Sector;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Modelos de tarefa (ex.: Devolução de valores) e vários anexos por tarefa (imagens e PDF).
 */
class ModelosEAnexosTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $grupo, ?string $setor = null): User
    {
        $usuario = User::factory()->create();
        $usuario->accessGroups()->attach(AccessGroup::where('slug', $grupo)->firstOrFail()->id);

        if ($setor) {
            $usuario->forceFill(['sector_id' => Sector::where('name', $setor)->value('id')])->save();
        }

        return $usuario;
    }

    private function devolucao(): TaskTemplate
    {
        return TaskTemplate::where('name', 'Devolução de valores')->firstOrFail(); // criado pela migration
    }

    private function pedidoDeDevolucao(array $respostas = [], array $extra = []): array
    {
        return array_merge([
            'task_template_id' => $this->devolucao()->id,
            'title' => 'Devolução de valores',
            'deadline' => now()->addDays(3)->toDateString(),
            'priority' => '2',
            'respostas' => array_merge([
                'cliente' => '12345 - Maria Souza',
                'data_solicitacao' => now()->toDateString(),
                'valor' => '1.250,90',
                'tipo_documento' => 'O.S.',
                'numero_documento' => 'OS-998',
                'dados_bancarios' => "Banco X\nAg 0001 / CC 12345-6",
                'motivo' => 'Peça não chegou',
            ], $respostas),
        ], $extra);
    }

    // ------------------------------------------------------------------ Modelos

    public function test_nova_tarefa_mostra_a_escolha_de_modelos(): void
    {
        $this->actingAs($this->usuario('solicitante'))->get('/tasks/create')
            ->assertOk()
            ->assertSee('Devolução de valores')
            ->assertSee('Tarefa livre');

        $this->actingAs($this->usuario('solicitante'))->get('/tasks/create?livre=1')
            ->assertOk()
            ->assertSee('Contexto do problema');
    }

    public function test_lista_de_tarefas_tem_o_botao_nova_tarefa_com_os_modelos(): void
    {
        $this->actingAs($this->usuario('solicitante'))->get('/')
            ->assertOk()
            ->assertSee('Nova tarefa')
            ->assertSee('/tasks/create?modelo='.$this->devolucao()->id, false)
            ->assertSee('/tasks/create?livre=1', false)
            ->assertDontSee('Criar Tarefas');
    }

    public function test_formulario_do_modelo_mostra_campos_e_instrucoes(): void
    {
        $this->actingAs($this->usuario('solicitante'))->get('/tasks/create?modelo='.$this->devolucao()->id)
            ->assertOk()
            ->assertSee('Código DMS e nome do cliente')
            ->assertSee('Valor da devolução')
            ->assertSee('IMPORTANTE')
            ->assertDontSee('Contexto do problema');
    }

    public function test_abre_tarefa_pelo_modelo_vai_para_o_financeiro_e_guarda_as_respostas(): void
    {
        Storage::fake('local');
        $solicitante = $this->usuario('solicitante');

        $this->actingAs($solicitante)->post('/tasks', $this->pedidoDeDevolucao([], [
            'anexos' => [
                UploadedFile::fake()->create('comprovante.pdf', 200, 'application/pdf'),
                UploadedFile::fake()->image('identidade.jpg'),
            ],
        ]))->assertSessionHasNoErrors();

        $tarefa = Task::firstOrFail();
        $this->assertSame(Sector::where('name', 'Financeiro')->value('id'), $tarefa->sector_id);
        $this->assertSame($this->devolucao()->id, $tarefa->task_template_id);
        $this->assertSame('', (string) $tarefa->description, 'Observações são opcionais em tarefa de modelo');

        $valor = collect($tarefa->respostas)->firstWhere('chave', 'valor');
        $this->assertSame('1250.90', $valor['valor']);
        $this->assertSame('R$ 1.250,90', TaskTemplate::formatar($valor));
        $this->assertCount(2, $tarefa->anexos);

        $this->actingAs($solicitante)->get('/tasks/'.$tarefa->id)
            ->assertOk()
            ->assertSee('Dados do pedido')
            ->assertSee('12345 - Maria Souza')
            ->assertSee('R$ 1.250,90')
            ->assertSee('comprovante.pdf');
    }

    public function test_campos_obrigatorios_e_opcoes_sao_conferidos(): void
    {
        $this->actingAs($this->usuario('solicitante'))
            ->post('/tasks', $this->pedidoDeDevolucao(['cliente' => '', 'tipo_documento' => 'Outro', 'valor' => 'abc']))
            ->assertSessionHasErrors(['respostas.cliente', 'respostas.tipo_documento', 'respostas.valor']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_modelo_desativado_nao_abre_tarefa(): void
    {
        $this->devolucao()->update(['ativo' => false]);

        $this->actingAs($this->usuario('solicitante'))
            ->post('/tasks', $this->pedidoDeDevolucao())
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_editar_tarefa_de_modelo_atualiza_respostas_mesmo_se_o_modelo_mudar(): void
    {
        $solicitante = $this->usuario('solicitante');
        $this->actingAs($solicitante)->post('/tasks', $this->pedidoDeDevolucao());
        $tarefa = Task::firstOrFail();

        // Mudar o modelo depois não altera a tarefa já aberta
        $this->devolucao()->update(['campos' => [['chave' => 'outro', 'rotulo' => 'Outro', 'tipo' => 'texto', 'obrigatorio' => true, 'opcoes' => []]]]);

        $this->actingAs($solicitante)->put('/tasks/update/'.$tarefa->id, $this->pedidoDeDevolucao(['valor' => '99,00']))
            ->assertSessionHasNoErrors();

        $valor = collect($tarefa->fresh()->respostas)->firstWhere('chave', 'valor');
        $this->assertSame('99.00', $valor['valor']);
    }

    public function test_administrador_cria_modelo_com_lista_de_opcoes(): void
    {
        $admin = $this->usuario('administrador');

        $this->actingAs($admin)->post('/admin/modelos', [
            'name' => 'Troca de senha do DMS',
            'sector_id' => Sector::where('name', 'TI')->value('id'),
            'ativo' => '1',
            'campos' => [
                ['rotulo' => 'Usuário do DMS', 'tipo' => 'texto', 'obrigatorio' => '1'],
                ['rotulo' => 'Loja', 'tipo' => 'opcoes', 'opcoes' => "Matriz\nFilial"],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(); // se der erro 500, o Laravel mostra a exceção aqui

        $modelo = TaskTemplate::where('name', 'Troca de senha do DMS')->firstOrFail();
        $this->assertSame('usuario_do_dms', $modelo->campos[0]['chave']);
        $this->assertTrue($modelo->campos[0]['obrigatorio']);
        $this->assertFalse($modelo->campos[1]['obrigatorio']);
        $this->assertSame(['Matriz', 'Filial'], $modelo->campos[1]['opcoes']);
    }

    public function test_lista_de_opcoes_precisa_de_duas_opcoes(): void
    {
        $this->actingAs($this->usuario('administrador'))->post('/admin/modelos', [
            'name' => 'Teste',
            'campos' => [['rotulo' => 'Tipo', 'tipo' => 'opcoes', 'opcoes' => 'Só uma']],
        ])->assertSessionHasErrors('campos.0.opcoes');
    }

    public function test_so_quem_tem_permissao_gerencia_modelos(): void
    {
        $this->actingAs($this->usuario('tecnico', 'TI'))->get('/admin/modelos')->assertForbidden();
        $this->actingAs($this->usuario('administrador'))->get('/admin/modelos')->assertOk()->assertSee('Devolução de valores');
    }

    public function test_modelos_enviados_ja_vem_cadastrados(): void
    {
        foreach ([
            'Cancelamento de nota fiscal',
            'Prestação de contas do fundo reserva - PTS',
            'Devolução financeira de veículos - VDC',
            'Transferência de adiantamento de cliente',
            'Lançamento de comprovante +24h',
            'Solicitação de equipamentos de informática',
        ] as $nome) {
            $this->assertTrue(TaskTemplate::where('name', $nome)->where('ativo', true)->exists(), "Faltou o modelo {$nome}");
        }

        $this->assertSame(Sector::where('name', 'TI')->value('id'), TaskTemplate::where('name', 'Solicitação de equipamentos de informática')->value('sector_id'));
    }

    public function test_modelo_sugere_a_prioridade_e_lista_as_lojas(): void
    {
        \App\Models\Store::create(['name' => 'Loja Matriz']);
        $modelo = TaskTemplate::where('name', 'Solicitação de equipamentos de informática')->firstOrFail();

        $this->actingAs($this->usuario('solicitante'))->get('/tasks/create?modelo='.$modelo->id)
            ->assertOk()
            ->assertSee('<option value="Loja Matriz"', false)
            ->assertSee('name="priority" value="3" checked', false);
    }

    public function test_campo_loja_so_aceita_loja_cadastrada(): void
    {
        \App\Models\Store::create(['name' => 'Loja Matriz']);
        $modelo = TaskTemplate::where('name', 'Solicitação de equipamentos de informática')->firstOrFail();
        $dados = [
            'task_template_id' => $modelo->id,
            'title' => 'Monitor novo',
            'deadline' => now()->addDays(3)->toDateString(),
            'priority' => '3',
            'respostas' => ['loja' => 'Loja Inventada', 'setor' => 'TI', 'usuario' => 'Ana', 'equipamento' => 'Monitor 24"'],
        ];
        $solicitante = $this->usuario('solicitante');

        $this->actingAs($solicitante)->post('/tasks', $dados)->assertSessionHasErrors('respostas.loja');

        $dados['respostas']['loja'] = 'Loja Matriz';
        $this->actingAs($solicitante)->post('/tasks', $dados)->assertSessionHasNoErrors();
        $this->assertSame(Sector::where('name', 'TI')->value('id'), Task::firstOrFail()->sector_id);
    }

    public function test_filtro_por_tipo_de_tarefa(): void
    {
        $solicitante = $this->usuario('solicitante');
        $this->actingAs($solicitante)->post('/tasks', $this->pedidoDeDevolucao([], ['title' => 'Devolucao da Maria']));
        Task::create(['title' => 'Mouse quebrado', 'description' => 'x', 'deadline' => now()->addDay()->toDateString(), 'priority' => 1, 'user_id' => $solicitante->id]);

        $admin = $this->usuario('administrador');
        $this->actingAs($admin)->get('/?modelo='.$this->devolucao()->id)->assertSee('Devolucao da Maria')->assertDontSee('Mouse quebrado');
        $this->actingAs($admin)->get('/?modelo=livre')->assertSee('Mouse quebrado')->assertDontSee('Devolucao da Maria');
    }

    // ------------------------------------------------------------------ Anexos

    public function test_quem_ve_a_tarefa_anexa_e_abre_os_arquivos(): void
    {
        Storage::fake('local');
        $dono = $this->usuario('solicitante');
        $outro = $this->usuario('solicitante');
        $this->actingAs($dono)->post('/tasks', $this->pedidoDeDevolucao());
        $tarefa = Task::firstOrFail();

        $this->actingAs($dono)->post('/tasks/'.$tarefa->id.'/anexos', [
            'anexos' => [UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $anexo = $tarefa->anexos()->firstOrFail();

        $this->actingAs($dono)->get('/tasks/'.$tarefa->id.'/anexos/'.$anexo->id)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($outro)->get('/tasks/'.$tarefa->id.'/anexos/'.$anexo->id)->assertNotFound();
        $this->actingAs($outro)->post('/tasks/'.$tarefa->id.'/anexos', [
            'anexos' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
        ])->assertForbidden();
    }

    public function test_anexo_nao_abre_por_outra_tarefa(): void
    {
        Storage::fake('local');
        $dono = $this->usuario('solicitante');
        $this->actingAs($dono)->post('/tasks', $this->pedidoDeDevolucao(['cliente' => 'A'], ['anexos' => [UploadedFile::fake()->image('a.jpg')]]));
        $this->actingAs($dono)->post('/tasks', $this->pedidoDeDevolucao(['cliente' => 'B']));
        [$primeira, $segunda] = Task::orderBy('id')->get();
        $anexo = $primeira->anexos()->firstOrFail();

        // Mesmo dono, mas o anexo não pertence à segunda tarefa
        $this->actingAs($dono)->get('/tasks/'.$segunda->id.'/anexos/'.$anexo->id)->assertNotFound();
    }

    public function test_nao_aceita_arquivo_de_outro_tipo_nem_grande_demais(): void
    {
        Storage::fake('local');
        $dono = $this->usuario('solicitante');
        $this->actingAs($dono)->post('/tasks', $this->pedidoDeDevolucao());
        $tarefa = Task::firstOrFail();

        $this->actingAs($dono)->post('/tasks/'.$tarefa->id.'/anexos', [
            'anexos' => [UploadedFile::fake()->create('planilha.exe', 10, 'application/x-msdownload')],
        ])->assertSessionHasErrors('anexos.0');

        $this->actingAs($dono)->post('/tasks/'.$tarefa->id.'/anexos', [
            'anexos' => [UploadedFile::fake()->create('grande.pdf', 11000, 'application/pdf')],
        ])->assertSessionHasErrors('anexos.0');

        $this->assertSame(0, $tarefa->anexos()->count());
    }

    public function test_quem_anexou_remove_e_o_arquivo_some(): void
    {
        Storage::fake('local');
        $dono = $this->usuario('solicitante');
        $this->actingAs($dono)->post('/tasks', $this->pedidoDeDevolucao([], ['anexos' => [UploadedFile::fake()->image('a.jpg')]]));
        $tarefa = Task::firstOrFail();
        $anexo = $tarefa->anexos()->firstOrFail();

        $atendente = $this->usuario('tecnico', 'Financeiro');
        $this->actingAs($atendente)->delete('/tasks/'.$tarefa->id.'/anexos/'.$anexo->id)->assertForbidden();

        $this->actingAs($dono)->delete('/tasks/'.$tarefa->id.'/anexos/'.$anexo->id)->assertRedirect();
        $this->assertNull(TaskAttachment::find($anexo->id));
        Storage::disk('local')->assertMissing('task-attachments/'.$anexo->arquivo);
    }
}
