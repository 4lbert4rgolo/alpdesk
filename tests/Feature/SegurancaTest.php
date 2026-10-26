<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SegurancaTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $grupo): User
    {
        $usuario = User::factory()->create();
        $usuario->accessGroups()->attach(AccessGroup::where('slug', $grupo)->firstOrFail()->id);

        return $usuario;
    }

    private function dadosDaTarefa(array $extra = []): array
    {
        return array_merge([
            'title' => 'Computador não liga',
            'description' => 'A tela fica preta.',
            'deadline' => now()->addDays(3)->toDateString(),
            'priority' => '0',
        ], $extra);
    }

    private function criarTarefa(User $dono, array $extra = []): Task
    {
        return Task::create(array_merge([
            'title' => 'Tarefa de teste',
            'description' => 'Descrição',
            'deadline' => now()->addDays(2)->toDateString(),
            'priority' => 0,
            'items' => [],
            'user_id' => $dono->id,
        ], $extra));
    }

    public function test_visitante_e_enviado_ao_login(): void
    {
        foreach (['/', '/tasks/1', '/dashboard', '/equipamentos'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_arquivo_php_nao_e_aceito_como_imagem(): void
    {
        Storage::fake('local');
        $usuario = $this->usuario('tecnico');

        $this->actingAs($usuario)
            ->post('/tasks', $this->dadosDaTarefa([
                'image' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo "x";'),
            ]))
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_imagem_valida_vai_para_a_pasta_privada(): void
    {
        Storage::fake('local');
        $usuario = $this->usuario('tecnico');

        $this->actingAs($usuario)
            ->post('/tasks', $this->dadosDaTarefa([
                'image' => UploadedFile::fake()->image('foto.jpg', 100, 100),
            ]))
            ->assertSessionHasNoErrors();

        $tarefa = Task::firstOrFail();

        Storage::disk('local')->assertExists('task-images/'.$tarefa->image);
        $this->assertFileDoesNotExist(public_path('img/tasks/'.$tarefa->image));
    }

    public function test_solicitante_nao_ve_tarefa_nem_imagem_de_outra_pessoa(): void
    {
        Storage::fake('local');
        $dono = $this->usuario('solicitante');
        $outro = $this->usuario('solicitante');
        $tarefa = $this->criarTarefa($dono, ['image' => 'abc.jpg']);
        Storage::disk('local')->put('task-images/abc.jpg', 'conteudo');

        $this->actingAs($outro)->get('/tasks/'.$tarefa->id)->assertNotFound();
        $this->actingAs($outro)->get('/tasks/'.$tarefa->id.'/imagem')->assertNotFound();

        $this->actingAs($dono)->get('/tasks/'.$tarefa->id.'/imagem')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_solicitante_ve_so_as_proprias_tarefas_na_lista(): void
    {
        $dono = $this->usuario('solicitante');
        $outro = $this->usuario('solicitante');
        $this->criarTarefa($dono, ['title' => 'Impressora do caixa']);
        $this->criarTarefa($outro, ['title' => 'Tarefa reservada de outra pessoa']);

        $this->actingAs($dono)->get('/')
            ->assertOk()
            ->assertSee('Impressora do caixa')
            ->assertDontSee('Tarefa reservada de outra pessoa');
    }

    public function test_tecnico_ve_as_tarefas_de_todos(): void
    {
        $dono = $this->usuario('solicitante');
        $tecnico = $this->usuario('tecnico');
        $tarefa = $this->criarTarefa($dono);

        $this->actingAs($tecnico)->get('/tasks/'.$tarefa->id)->assertOk();
    }

    public function test_tarefa_pode_ser_criada_sem_imagem(): void
    {
        $usuario = $this->usuario('tecnico');

        $this->actingAs($usuario)
            ->post('/tasks', $this->dadosDaTarefa())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseCount('tasks', 1);
        $this->assertNull(Task::firstOrFail()->image);
    }

    public function test_titulo_com_mais_de_60_caracteres_e_aceito(): void
    {
        $usuario = $this->usuario('tecnico');

        $this->actingAs($usuario)
            ->post('/tasks', $this->dadosDaTarefa(['title' => str_repeat('A', 120)]))
            ->assertSessionHasNoErrors();

        $this->assertSame(120, strlen(Task::firstOrFail()->title));
    }
}
