<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\HelpArticle;
use App\Models\Sector;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tela Início (Home) separada da lista de tarefas (/tarefas).
 */
class HomeTest extends TestCase
{
    use RefreshDatabase;

    private function pessoa(string $grupo = 'solicitante', ?Sector $setor = null): User
    {
        $usuario = User::factory()->create();
        $usuario->accessGroups()->attach(AccessGroup::where('slug', $grupo)->firstOrFail()->id);
        $usuario->forceFill(['sector_id' => $setor?->id])->save();

        return $usuario;
    }

    private function conteudo(string $tipo, string $titulo, array $extra = [], array $setores = []): HelpArticle
    {
        $artigo = HelpArticle::create(array_merge([
            'tipo' => $tipo,
            'titulo' => $titulo,
            'conteudo' => 'Texto de '.$titulo,
            'publicado' => true,
            'publicado_em' => now(),
        ], $extra));
        $artigo->setores()->sync(collect($setores)->map->id->all());

        return $artigo;
    }

    public function test_inicio_e_tarefas_sao_telas_separadas(): void
    {
        $pessoa = $this->pessoa();

        $this->actingAs($pessoa)->get('/')
            ->assertOk()
            ->assertSee('Comunicados')
            ->assertSee('Minhas tarefas')
            ->assertSee('href="/tarefas"', false);

        $this->actingAs($pessoa)->get('/tarefas')->assertOk()->assertDontSee('Tutoriais recentes');
    }

    public function test_mostra_comunicados_do_setor_da_pessoa(): void
    {
        $vendas = Sector::firstOrCreate(['name' => 'Vendas (teste)']);
        $financeiro = Sector::firstOrCreate(['name' => 'Financeiro (teste)']);

        $this->conteudo('comunicado', 'Confraternização de fim de ano');
        $this->conteudo('comunicado', 'Meta de vendas de dezembro', [], [$vendas]);
        $this->conteudo('comunicado', 'Fechamento do caixa', [], [$financeiro]);
        $this->conteudo('comunicado', 'Rascunho do RH', ['publicado' => false, 'publicado_em' => null]);

        $this->actingAs($this->pessoa('solicitante', $vendas))->get('/')
            ->assertSee('Confraternização de fim de ano')
            ->assertSee('Meta de vendas de dezembro')
            ->assertDontSee('Fechamento do caixa')
            ->assertDontSee('Rascunho do RH');
    }

    public function test_comunicado_fixado_aparece_primeiro(): void
    {
        $this->conteudo('comunicado', 'Comunicado novo', ['publicado_em' => now()]);
        $this->conteudo('comunicado', 'Comunicado fixado antigo', ['fixado' => true, 'publicado_em' => now()->subMonth()]);

        $this->actingAs($this->pessoa())->get('/')
            ->assertSeeInOrder(['Comunicado fixado antigo', 'Comunicado novo']);
    }

    public function test_mostra_circulares_pendentes_e_tutoriais(): void
    {
        $this->conteudo('circular', 'Novo horário das lojas', ['exige_ciencia' => true]);
        $this->conteudo('tutorial', 'Como corrigir o CEST');

        $this->actingAs($this->pessoa())->get('/')
            ->assertSee('para confirmar a leitura')
            ->assertSee('Novo horário das lojas')
            ->assertSee('Como corrigir o CEST');
    }

    public function test_resumo_conta_so_as_minhas_tarefas_em_aberto(): void
    {
        $eu = $this->pessoa();
        $outro = $this->pessoa();

        $tarefa = fn (User $dono, string $titulo, array $extra = []) => Task::create(array_merge([
            'title' => $titulo,
            'description' => 'Descrição',
            'deadline' => now()->addDays(2)->toDateString(),
            'priority' => 2,
            'items' => [],
            'user_id' => $dono->id,
        ], $extra));

        $tarefa($eu, 'Minha aberta');
        $tarefa($eu, 'Minha aguardando', ['status' => 'aguardando']);
        $tarefa($eu, 'Minha concluida', ['status' => 'concluida', 'solucao' => 'Resolvido', 'concluida_em' => now()]);
        $tarefa($outro, 'Tarefa de outra pessoa');

        $this->actingAs($eu)->get('/')
            ->assertSee('Minha aberta')
            ->assertSee('Minha aguardando')
            ->assertDontSee('Minha concluida')
            ->assertDontSee('Tarefa de outra pessoa')
            ->assertSeeText('2 em aberto')
            ->assertSeeText('1 aguardando');
    }

    public function test_tipo_comunicado_aparece_na_central_de_ajuda(): void
    {
        $this->conteudo('comunicado', 'Aviso geral');

        $this->actingAs($this->pessoa())->get(route('ajuda.index', ['tipo' => 'comunicado']))
            ->assertOk()
            ->assertSee('Comunicados')
            ->assertSee('Aviso geral');
    }
}
