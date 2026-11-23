<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Cadastros simples usados em usuários e equipamentos: setores e lojas.
 * Uma tela só atende os dois; o {tipo} da rota diz qual lista está aberta.
 */
class CadastroController extends Controller
{
    private const TIPOS = [
        'setores' => [
            'modelo' => Sector::class,
            'tabela' => 'sectors',
            'titulo' => 'Setores',
            'novo' => 'Novo setor',
            'criado' => 'Setor cadastrado',
            'renomeado' => 'Setor renomeado',
            'excluido' => 'Setor excluído',
            'filtro' => 'setor',
            'coluna' => 'sector_id',
            'vazio' => 'sem setor',
            'tabelasLigadas' => ['users', 'equipment', 'tasks', 'task_templates'],
        ],
        'lojas' => [
            'modelo' => Store::class,
            'tabela' => 'stores',
            'titulo' => 'Lojas',
            'novo' => 'Nova loja',
            'criado' => 'Loja cadastrada',
            'renomeado' => 'Loja renomeada',
            'excluido' => 'Loja excluída',
            'filtro' => 'loja',
            'coluna' => 'store_id',
            'vazio' => 'sem loja',
            'tabelasLigadas' => ['users', 'equipment'],
        ],
    ];

    public function index(string $tipo)
    {
        $config = self::TIPOS[$tipo];

        return view('admin.cadastros.index', [
            'tipo' => $tipo,
            'config' => $config,
            'tipos' => self::TIPOS,
            // users_count e equipment_count: quantas pessoas e equipamentos usam cada item
            'itens' => $config['modelo']::withCount($tipo === 'setores' ? ['users', 'equipment', 'tasks'] : ['users', 'equipment'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, string $tipo)
    {
        $config = self::TIPOS[$tipo];

        $dados = $request->validate(
            ['name' => ['required', 'string', 'max:100', Rule::unique($config['tabela'], 'name')]],
            $this->mensagens()
        );

        $config['modelo']::create(['name' => $dados['name']] + $this->recebeTarefas($request, $tipo));

        return redirect()->route('admin.cadastros.index', $tipo)->with('msg', "{$config['criado']}: {$dados['name']}.");
    }

    public function update(Request $request, string $tipo, int $id)
    {
        $config = self::TIPOS[$tipo];
        $item = $config['modelo']::findOrFail($id);

        $dados = $request->validate(
            ['name' => ['required', 'string', 'max:100', Rule::unique($config['tabela'], 'name')->ignore($item->id)]],
            $this->mensagens()
        );

        $antes = $item->name;
        $item->update(['name' => $dados['name']] + $this->recebeTarefas($request, $tipo));

        $mensagem = $antes === $item->name ? "Cadastro salvo: {$item->name}." : "{$config['renomeado']}: {$antes} → {$item->name}.";

        return redirect()->route('admin.cadastros.index', $tipo)->with('msg', $mensagem);
    }

    /**
     * Exclui o setor ou a loja. O que estava ligado a ele (pessoas, equipamentos e,
     * no caso de setores, tarefas e modelos de tarefa) vai para o item escolhido em
     * "mover_para" ou fica vazio. Tudo numa transação: ou faz tudo, ou nada.
     */
    public function destroy(Request $request, string $tipo, int $id)
    {
        $config = self::TIPOS[$tipo];
        $item = $config['modelo']::findOrFail($id);

        $dados = $request->validate([
            'mover_para' => ['nullable', 'integer', Rule::exists($config['tabela'], 'id'), Rule::notIn([$item->id])],
        ], [
            'mover_para.exists' => 'Escolha um item da lista para receber os vínculos.',
            'mover_para.not_in' => 'Escolha um item diferente do que será excluído.',
        ]);

        $destino = ! empty($dados['mover_para']) ? $config['modelo']::find($dados['mover_para']) : null;
        $movidos = 0;

        DB::transaction(function () use ($config, $item, $destino, &$movidos) {
            foreach ($config['tabelasLigadas'] as $tabela) {
                $movidos += DB::table($tabela)->where($config['coluna'], $item->id)->update([$config['coluna'] => $destino?->id]);
            }

            $item->delete();
        });

        $mensagem = "{$config['excluido']}: {$item->name}.";

        if ($movidos > 0) {
            $mensagem .= $destino
                ? " {$movidos} vínculo(s) passaram para {$destino->name}."
                : " {$movidos} vínculo(s) ficaram {$config['vazio']}.";
        }

        return redirect()->route('admin.cadastros.index', $tipo)->with('msg', $mensagem);
    }

    /**
     * Só setores têm a marcação "Recebe tarefas" (aparecem como destino ao abrir uma tarefa).
     * Checkbox desmarcado não é enviado pelo navegador, por isso o boolean() vira false.
     */
    private function recebeTarefas(Request $request, string $tipo): array
    {
        return $tipo === 'setores' ? ['recebe_tarefas' => $request->boolean('recebe_tarefas')] : [];
    }

    private function mensagens(): array
    {
        return [
            'name.required' => 'Informe o nome.',
            'name.unique' => 'Já existe um cadastro com este nome.',
            'name.max' => 'Use no máximo 100 caracteres.',
        ];
    }
}
