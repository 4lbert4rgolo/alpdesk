<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\HelpArticle;
use App\Models\HelpAttachment;
use App\Models\Sector;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Central de Ajuda: perguntas frequentes, tutoriais, circulares e documentos,
 * com acesso por setor e loja e confirmação de leitura das circulares.
 */
class CentralAjudaTest extends TestCase
{
    use RefreshDatabase;

    private Sector $vendas;

    private Sector $financeiro;

    private Store $lojaCentro;

    private Store $lojaNorte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendas = Sector::firstOrCreate(['name' => 'Vendas (teste)']);
        $this->financeiro = Sector::firstOrCreate(['name' => 'Financeiro (teste)']);
        $this->lojaCentro = Store::firstOrCreate(['name' => 'Loja Centro (teste)']);
        $this->lojaNorte = Store::firstOrCreate(['name' => 'Loja Norte (teste)']);
    }

    private function pessoa(?Sector $setor = null, ?Store $loja = null, string $grupo = 'solicitante'): User
    {
        $usuario = User::factory()->create();
        $usuario->accessGroups()->attach(AccessGroup::where('slug', $grupo)->firstOrFail()->id);
        $usuario->forceFill(['sector_id' => $setor?->id, 'store_id' => $loja?->id])->save();

        return $usuario;
    }

    private function gestor(): User
    {
        // firstOrCreate: o mesmo teste pode chamar gestor() mais de uma vez
        $grupo = AccessGroup::firstOrCreate(
            ['slug' => 'comunicacao-teste'],
            ['name' => 'Comunicação (teste)', 'permissions' => ['faq.gerenciar']]
        );

        $usuario = User::factory()->create();
        $usuario->accessGroups()->attach($grupo->id);

        return $usuario;
    }

    private function artigo(array $dados = [], array $setores = [], array $lojas = []): HelpArticle
    {
        $artigo = HelpArticle::create(array_merge([
            'tipo' => 'tutorial',
            'titulo' => 'Como trocar o papel da impressora',
            'conteudo' => 'Abra a tampa e encaixe o rolo.',
            'publicado' => true,
            'publicado_em' => now(),
        ], $dados));

        $artigo->setores()->sync(collect($setores)->map->id->all());
        $artigo->lojas()->sync(collect($lojas)->map->id->all());

        return $artigo;
    }

    // ------------------------------------------------------------------ Quem vê

    public function test_conteudo_sem_setor_aparece_para_todos(): void
    {
        $artigo = $this->artigo();

        $this->actingAs($this->pessoa($this->vendas))->get(route('ajuda.index'))
            ->assertOk()->assertSee($artigo->titulo);

        $this->actingAs($this->pessoa())->get(route('ajuda.show', $artigo))->assertOk();
    }

    public function test_conteudo_de_um_setor_nao_aparece_para_outro(): void
    {
        $artigo = $this->artigo(['titulo' => 'Política de descontos'], [$this->vendas]);

        $this->actingAs($this->pessoa($this->vendas))->get(route('ajuda.show', $artigo))->assertOk();

        $outro = $this->pessoa($this->financeiro);
        $this->actingAs($outro)->get(route('ajuda.index'))->assertDontSee('Política de descontos');
        $this->actingAs($outro)->get(route('ajuda.show', $artigo))->assertNotFound();

        // Quem não tem setor no cadastro só vê o que é para todos
        $this->actingAs($this->pessoa())->get(route('ajuda.show', $artigo))->assertNotFound();
    }

    public function test_setor_e_loja_juntos(): void
    {
        $artigo = $this->artigo(['titulo' => 'Meta da Loja Centro'], [$this->vendas], [$this->lojaCentro]);

        $this->actingAs($this->pessoa($this->vendas, $this->lojaCentro))->get(route('ajuda.show', $artigo))->assertOk();
        $this->actingAs($this->pessoa($this->vendas, $this->lojaNorte))->get(route('ajuda.show', $artigo))->assertNotFound();
        $this->actingAs($this->pessoa($this->financeiro, $this->lojaCentro))->get(route('ajuda.show', $artigo))->assertNotFound();
    }

    public function test_rascunho_so_aparece_para_quem_gerencia(): void
    {
        $artigo = $this->artigo(['titulo' => 'Rascunho secreto', 'publicado' => false, 'publicado_em' => null]);

        $this->actingAs($this->pessoa())->get(route('ajuda.show', $artigo))->assertNotFound();
        $this->actingAs($this->gestor())->get(route('ajuda.show', $artigo))->assertOk()->assertSee('Rascunho');
    }

    public function test_busca_encontra_pelo_texto(): void
    {
        $this->artigo(['titulo' => 'Férias', 'conteudo' => 'Peça com 30 dias de antecedência.']);
        $this->artigo(['titulo' => 'Impressora']);

        $this->actingAs($this->pessoa())->get(route('ajuda.index', ['q' => 'antecedência']))
            ->assertSee('Férias')->assertDontSee('Impressora');
    }

    public function test_html_digitado_no_texto_nao_vira_codigo(): void
    {
        $artigo = $this->artigo(['conteudo' => "**Importante**\n\n<script>alert(1)</script>"]);

        $this->actingAs($this->pessoa())->get(route('ajuda.show', $artigo))
            ->assertSee('<strong>Importante</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    // ------------------------------------------------------------------ Publicação

    public function test_quem_nao_tem_permissao_nao_publica(): void
    {
        $pessoa = $this->pessoa($this->vendas);

        $this->actingAs($pessoa)->get(route('ajuda.create'))->assertForbidden();
        $this->actingAs($pessoa)->post(route('ajuda.store'), ['tipo' => 'faq', 'titulo' => 'X'])->assertForbidden();
    }

    public function test_gestor_publica_para_setores_e_lojas_com_arquivo(): void
    {
        Storage::fake('local');

        $this->actingAs($this->gestor())->post(route('ajuda.store'), [
            'tipo' => 'documento',
            'titulo' => 'Tabela de comissões',
            'categoria' => 'Vendas',
            'publicado' => '1',
            'setores' => [$this->vendas->id],
            'lojas' => [$this->lojaCentro->id, $this->lojaNorte->id],
            'anexos' => [UploadedFile::fake()->create('comissoes.pdf', 200, 'application/pdf')],
        ])->assertRedirect();

        $artigo = HelpArticle::where('titulo', 'Tabela de comissões')->firstOrFail();

        $this->assertTrue($artigo->publicado);
        $this->assertNotNull($artigo->publicado_em);
        $this->assertEqualsCanonicalizing([$this->vendas->id], $artigo->setores->modelKeys());
        $this->assertCount(2, $artigo->lojas);
        $this->assertCount(1, $artigo->anexos);
        Storage::disk('local')->assertExists($artigo->anexos->first()->caminho());
    }

    public function test_arquivo_so_abre_para_quem_ve_o_conteudo(): void
    {
        Storage::fake('local');

        $artigo = $this->artigo([], [$this->vendas]);
        HelpAttachment::salvar($artigo, [UploadedFile::fake()->create('manual.pdf', 50, 'application/pdf')], null);
        $anexo = $artigo->anexos()->first();

        $this->actingAs($this->pessoa($this->vendas))->get(route('ajuda.anexo', [$artigo, $anexo]))->assertOk();
        $this->actingAs($this->pessoa($this->financeiro))->get(route('ajuda.anexo', [$artigo, $anexo]))->assertNotFound();
    }

    public function test_arquivo_php_nao_e_aceito(): void
    {
        Storage::fake('local');

        $this->actingAs($this->gestor())->post(route('ajuda.store'), [
            'tipo' => 'documento',
            'titulo' => 'Teste',
            'anexos' => [UploadedFile::fake()->createWithContent('virus.php', '<?php echo 1;')],
        ])->assertSessionHasErrors('anexos.0');

        $this->assertDatabaseCount('help_articles', 0);
    }

    public function test_excluir_apaga_os_arquivos(): void
    {
        Storage::fake('local');

        $artigo = $this->artigo();
        HelpAttachment::salvar($artigo, [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], null);
        $caminho = $artigo->anexos()->first()->caminho();

        $this->actingAs($this->gestor())->delete(route('ajuda.destroy', $artigo))->assertRedirect();

        $this->assertDatabaseCount('help_articles', 0);
        Storage::disk('local')->assertMissing($caminho);
    }

    // ------------------------------------------------------------------ Circulares

    public function test_circular_confirmacao_de_leitura(): void
    {
        $circular = $this->artigo(['tipo' => 'circular', 'titulo' => 'Novo horário', 'exige_ciencia' => true]);
        $pessoa = $this->pessoa($this->vendas);

        // Aparece como pendente na lista e no menu
        $this->actingAs($pessoa)->get(route('ajuda.index'))->assertSee('para confirmar a leitura');
        $this->assertSame(1, HelpArticle::query()->visivelPara($pessoa)->pendentesDeCiencia($pessoa)->count());

        $this->actingAs($pessoa)->post(route('ajuda.ciente', $circular))->assertRedirect(route('ajuda.show', $circular));
        $this->actingAs($pessoa)->post(route('ajuda.ciente', $circular)); // segundo clique não duplica

        $this->assertSame(1, $circular->leituras()->count());
        $this->assertSame(0, HelpArticle::query()->visivelPara($pessoa)->pendentesDeCiencia($pessoa)->count());
        $this->actingAs($pessoa)->get(route('ajuda.show', $circular))->assertSee('Você confirmou a leitura');
    }

    public function test_nao_confirma_circular_de_outro_setor(): void
    {
        $circular = $this->artigo(['tipo' => 'circular', 'exige_ciencia' => true], [$this->vendas]);

        $this->actingAs($this->pessoa($this->financeiro))->post(route('ajuda.ciente', $circular))->assertNotFound();
        $this->assertSame(0, $circular->leituras()->count());
    }

    public function test_relatorio_mostra_quem_leu_e_quem_falta(): void
    {
        $circular = $this->artigo(['tipo' => 'circular', 'exige_ciencia' => true], [$this->vendas]);

        $leu = $this->pessoa($this->vendas);
        $falta = $this->pessoa($this->vendas);
        $outroSetor = $this->pessoa($this->financeiro);

        $circular->leituras()->create(['user_id' => $leu->id, 'lido_em' => now()]);

        $this->actingAs($this->gestor())->get(route('ajuda.leituras', $circular))
            ->assertOk()
            ->assertSee($leu->name)
            ->assertSee($falta->name)
            ->assertDontSee($outroSetor->name)
            ->assertSeeText('1 de 2 confirmaram');

        $this->actingAs($this->gestor())->get(route('ajuda.leituras', [$circular, 'filtro' => 'pendentes']))
            ->assertSee($falta->name)
            ->assertDontSee($leu->name);

        // Quem não gerencia não vê o relatório
        $this->actingAs($leu)->get(route('ajuda.leituras', $circular))->assertForbidden();
    }

    public function test_pedir_ciencia_de_novo_apaga_as_confirmacoes(): void
    {
        $circular = $this->artigo(['tipo' => 'circular', 'exige_ciencia' => true]);
        $circular->leituras()->create(['user_id' => $this->pessoa()->id, 'lido_em' => now()]);

        $this->actingAs($this->gestor())->put(route('ajuda.update', $circular), [
            'tipo' => 'circular',
            'titulo' => 'Novo horário (corrigido)',
            'publicado' => '1',
            'exige_ciencia' => '1',
            'pedir_ciencia_novamente' => '1',
        ])->assertRedirect();

        $this->assertSame(0, $circular->leituras()->count());
        $this->assertSame('Novo horário (corrigido)', $circular->fresh()->titulo);
    }

    // ------------------------------------------------------------------ Vídeos

    public function test_aceita_video_de_ate_200_mb(): void
    {
        Storage::fake('local');

        $this->actingAs($this->gestor())->post(route('ajuda.store'), [
            'tipo' => 'tutorial',
            'titulo' => 'Como abrir um chamado',
            'publicado' => '1',
            'anexos' => [UploadedFile::fake()->create('aula.mp4', 150 * 1024, 'video/mp4')], // 150 MB
        ])->assertSessionHasNoErrors()->assertRedirect();

        $anexo = HelpArticle::where('titulo', 'Como abrir um chamado')->firstOrFail()->anexos->first();

        $this->assertTrue($anexo->ehVideo());
        $this->assertTrue($anexo->abreNoNavegador());
    }

    public function test_video_acima_de_200_mb_e_recusado(): void
    {
        Storage::fake('local');

        $this->actingAs($this->gestor())->post(route('ajuda.store'), [
            'tipo' => 'tutorial',
            'titulo' => 'Vídeo grande demais',
            'anexos' => [UploadedFile::fake()->create('longo.mp4', 210 * 1024, 'video/mp4')],
        ])->assertSessionHasErrors('anexos.0');
    }

    public function test_documento_continua_limitado_a_20_mb(): void
    {
        Storage::fake('local');

        $this->actingAs($this->gestor())->post(route('ajuda.store'), [
            'tipo' => 'documento',
            'titulo' => 'PDF grande demais',
            'anexos' => [UploadedFile::fake()->create('manual.pdf', 30 * 1024, 'application/pdf')], // 30 MB
        ])->assertSessionHasErrors('anexos.0');
    }

    public function test_video_toca_na_pagina_e_aceita_pedido_em_pedacos(): void
    {
        Storage::fake('local');

        $artigo = $this->artigo();
        // O conteúdo "xxxx" não é um vídeo de verdade, então o PHP o detectaria como texto.
        // mimeType() informa ao arquivo falso qual tipo ele deve dizer que é.
        $arquivo = UploadedFile::fake()->createWithContent('aula.mp4', str_repeat('x', 5000))->mimeType('video/mp4');
        HelpAttachment::salvar($artigo, [$arquivo], null);
        $video = $artigo->anexos()->first();
        $pessoa = $this->pessoa();

        $this->actingAs($pessoa)->get(route('ajuda.show', $artigo))->assertSee('<video', false);

        // "Range" é como o navegador pede só um pedaço do vídeo (para pular para o meio, por exemplo)
        $this->actingAs($pessoa)
            ->get(route('ajuda.anexo', [$artigo, $video]), ['Range' => 'bytes=0-99'])
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-99/5000');
    }
}
