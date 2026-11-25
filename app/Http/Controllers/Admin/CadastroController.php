<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\Store;
use App\Rules\Cnpj;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Cadastros simples usados em usuários e equipamentos: setores e empresas.
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
        'empresas' => [
            'modelo' => Store::class,
            'tabela' => 'stores',
            'titulo' => 'Empresas',
            'novo' => 'Nova empresa',
            'criado' => 'Empresa cadastrada',
            'renomeado' => 'Empresa renomeada',
            'excluido' => 'Empresa excluída',
            'filtro' => 'loja',
            'coluna' => 'store_id',
            'vazio' => 'sem empresa',
            'tabelasLigadas' => ['users', 'equipment'],
        ],
    ];

    public function index(string $tipo)
    {
        $tipo = self::tipo($tipo);
        $config = self::TIPOS[$tipo];

        return view('admin.cadastros.index', [
            'tipo' => $tipo,
            'config' => $config,
            'tipos' => self::TIPOS,
            // users_count e equipment_count: quantas pessoas e equipamentos usam cada item
            'itens' => $config['modelo']::withCount($tipo === 'setores' ? ['users', 'equipment', 'tasks'] : ['users', 'equipment'])
                ->when($tipo === 'empresas', fn ($q) => $q->with('empresaResponsavel'))
                ->orderBy('name')
                ->get(),
        ]);
    }

    /** Tela própria com os dados da empresa (nome fantasia, razão social, CNPJ, endereço, Encarregado) */
    public function edit(string $tipo, int $id)
    {
        abort_unless(self::tipo($tipo) === 'empresas', 404);

        $empresa = Store::findOrFail($id);

        return view('admin.cadastros.empresa', [
            'empresa' => $empresa,
            // Opções de "empresa responsável": só empresas de verdade, sem o próprio cadastro
            'empresas' => Store::empresas()->whereKeyNot($empresa->id)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $tipo)
    {
        $tipo = self::tipo($tipo);
        $config = self::TIPOS[$tipo];

        $dados = $request->validate(
            ['name' => ['required', 'string', 'max:100', Rule::unique($config['tabela'], 'name')]] + $this->regrasEmpresa($tipo),
            $this->mensagens()
        );

        $config['modelo']::create(['name' => $dados['name']] + $this->recebeTarefas($request, $tipo) + $this->dadosEmpresa($dados, $tipo));

        return redirect()->route('admin.cadastros.index', $tipo)->with('msg', "{$config['criado']}: {$dados['name']}.");
    }

    public function update(Request $request, string $tipo, int $id)
    {
        $tipo = self::tipo($tipo);
        $config = self::TIPOS[$tipo];
        $item = $config['modelo']::findOrFail($id);

        $dados = $request->validate(
            ['name' => ['required', 'string', 'max:100', Rule::unique($config['tabela'], 'name')->ignore($item->id)]] + $this->regrasEmpresa($tipo),
            $this->mensagens()
        );

        $antes = $item->name;
        $item->update(['name' => $dados['name']] + $this->recebeTarefas($request, $tipo) + $this->dadosEmpresa($dados, $tipo));

        $mensagem = $antes === $item->name ? "Cadastro salvo: {$item->name}." : "{$config['renomeado']}: {$antes} → {$item->name}.";

        return redirect()->route('admin.cadastros.index', $tipo)->with('msg', $mensagem);
    }

    /**
     * Exclui o setor ou a empresa. O que estava ligado a ele (pessoas, equipamentos e,
     * no caso de setores, tarefas e modelos de tarefa) vai para o item escolhido em
     * "mover_para" ou fica vazio. Tudo numa transação: ou faz tudo, ou nada.
     */
    public function destroy(Request $request, string $tipo, int $id)
    {
        $tipo = self::tipo($tipo);
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

    /** "lojas" era o nome antigo; os links antigos continuam funcionando */
    private static function tipo(string $tipo): string
    {
        return $tipo === 'lojas' ? 'empresas' : $tipo;
    }

    /**
     * Dados que o termo LGPD pede de cada empresa (todos opcionais no cadastro,
     * mas o termo mostra um aviso enquanto faltarem).
     */
    private function regrasEmpresa(string $tipo): array
    {
        if ($tipo !== 'empresas') {
            return [];
        }

        return [
            // "empresa" (tem CNPJ) ou "local" (ex.: Escritório). Sem o campo, o tipo não muda.
            'tipo' => ['sometimes', Rule::in([Store::TIPO_EMPRESA, Store::TIPO_LOCAL])],
            // A responsável precisa ser uma empresa de verdade, e não o próprio cadastro
            'empresa_responsavel_id' => [
                'nullable', 'integer',
                Rule::exists('stores', 'id')->where('tipo', Store::TIPO_EMPRESA),
                Rule::notIn([(int) request()->route('id')]),
            ],
            'razao_social' => ['nullable', 'string', 'max:255'],
            // Cnpj: regra própria que confere os dígitos verificadores (app/Rules/Cnpj.php)
            'cnpj' => ['nullable', 'string', 'max:18', new Cnpj],
            'endereco' => ['nullable', 'string', 'max:255'],
            'encarregado_nome' => ['nullable', 'string', 'max:255'],
            'encarregado_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    private function dadosEmpresa(array $dados, string $tipo): array
    {
        if ($tipo !== 'empresas') {
            return [];
        }

        // Local (ex.: Escritório): não tem dados próprios de empresa; guarda só quem é a responsável
        $tipoAtual = $dados['tipo'] ?? Store::find(request()->route('id'))?->tipo ?? Store::TIPO_EMPRESA;

        if ($tipoAtual === Store::TIPO_LOCAL) {
            return [
                'tipo' => Store::TIPO_LOCAL,
                'empresa_responsavel_id' => $dados['empresa_responsavel_id'] ?? null,
                'razao_social' => null, 'cnpj' => null, 'endereco' => null,
                'encarregado_nome' => null, 'encarregado_email' => null,
            ];
        }

        $cnpj = filled($dados['cnpj'] ?? null) ? Cnpj::formatar($dados['cnpj']) : null;

        // O CNPJ é único: confere depois de formatar, para "11222333000181" e "11.222.333/0001-81" contarem como o mesmo
        if ($cnpj && Store::where('cnpj', $cnpj)->where('id', '!=', request()->route('id') ?? 0)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['cnpj' => 'Já existe uma empresa com este CNPJ.']);
        }

        return [
            'tipo' => Store::TIPO_EMPRESA,
            'empresa_responsavel_id' => null,
            'razao_social' => $dados['razao_social'] ?? null,
            'cnpj' => $cnpj,
            'endereco' => $dados['endereco'] ?? null,
            'encarregado_nome' => $dados['encarregado_nome'] ?? null,
            'encarregado_email' => $dados['encarregado_email'] ?? null,
        ];
    }

    private function mensagens(): array
    {
        return [
            'name.required' => 'Informe o nome.',
            'name.unique' => 'Já existe um cadastro com este nome.',
            'name.max' => 'Use no máximo 100 caracteres.',
            'encarregado_email.email' => 'Informe um e-mail válido para o Encarregado.',
            'empresa_responsavel_id.exists' => 'Escolha uma empresa (com CNPJ) como responsável.',
            'empresa_responsavel_id.not_in' => 'Um local não pode ser responsável por ele mesmo.',
        ];
    }
}
