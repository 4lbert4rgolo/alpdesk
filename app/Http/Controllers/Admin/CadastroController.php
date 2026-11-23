<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\Store;
use Illuminate\Http\Request;
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
            'itens' => $config['modelo']::withCount(['users', 'equipment'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $tipo)
    {
        $config = self::TIPOS[$tipo];

        $dados = $request->validate(
            ['name' => ['required', 'string', 'max:100', Rule::unique($config['tabela'], 'name')]],
            $this->mensagens()
        );

        $config['modelo']::create(['name' => $dados['name']]);

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
        $item->update(['name' => $dados['name']]);

        return redirect()->route('admin.cadastros.index', $tipo)->with('msg', "{$config['renomeado']}: {$antes} → {$item->name}.");
    }

    /**
     * Só exclui o que não está em uso, para ninguém perder, sem perceber,
     * o setor ou a loja de pessoas e equipamentos.
     */
    public function destroy(string $tipo, int $id)
    {
        $config = self::TIPOS[$tipo];
        $item = $config['modelo']::withCount(['users', 'equipment'])->findOrFail($id);

        if ($item->users_count > 0 || $item->equipment_count > 0) {
            return back()->withErrors([
                'name' => "\"{$item->name}\" está em uso por {$item->users_count} pessoa(s) e {$item->equipment_count} equipamento(s). Mude-os para outro item antes de excluir.",
            ]);
        }

        $item->delete();

        return redirect()->route('admin.cadastros.index', $tipo)->with('msg', "{$config['excluido']}: {$item->name}.");
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
