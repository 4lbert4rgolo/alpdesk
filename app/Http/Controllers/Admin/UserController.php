<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessGroup;
use App\Models\Sector;
use App\Models\Store;
use App\Models\User;
use App\Support\Permissoes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as RedefinirSenha;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Painel de usuários: criar, editar, ativar/desativar e ações de conta
 * (link para redefinir senha, remover a verificação em duas etapas, confirmar e-mail).
 *
 * Travas de segurança:
 * - o sistema nunca fica sem pelo menos um administrador ativo;
 * - ninguém desativa a própria conta nem tira o próprio acesso a esta tela;
 * - quem não é administrador não mexe em contas de administradores e só atribui
 *   grupos com permissões que ele mesmo tem (evita alguém se promover a administrador).
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $busca = trim((string) $request->query('q', ''));
        $grupo = (string) $request->query('grupo', '');
        $situacao = in_array($request->query('situacao'), ['ativos', 'desativados'], true) ? $request->query('situacao') : 'todos';
        $setor = (string) $request->query('setor', '');
        $loja = (string) $request->query('loja', '');

        $usuarios = User::with(['accessGroups', 'sector', 'store'])
            ->when($busca !== '', function ($consulta) use ($busca) {
                $consulta->where(fn ($q) => $q->where('name', 'like', "%{$busca}%")->orWhere('email', 'like', "%{$busca}%"));
            })
            ->when($grupo === 'sem', fn ($consulta) => $consulta->doesntHave('accessGroups'))
            ->when(ctype_digit($grupo), fn ($consulta) => $consulta->whereHas('accessGroups', fn ($g) => $g->where('access_groups.id', (int) $grupo)))
            ->when($setor === 'sem', fn ($consulta) => $consulta->whereNull('sector_id'))
            ->when(ctype_digit($setor), fn ($consulta) => $consulta->where('sector_id', (int) $setor))
            ->when($loja === 'sem', fn ($consulta) => $consulta->whereNull('store_id'))
            ->when(ctype_digit($loja), fn ($consulta) => $consulta->where('store_id', (int) $loja))
            ->when($situacao === 'ativos', fn ($consulta) => $consulta->where('ativo', true))
            ->when($situacao === 'desativados', fn ($consulta) => $consulta->where('ativo', false))
            ->orderByDesc('ativo')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.usuarios.index', [
            'usuarios' => $usuarios,
            'grupos' => AccessGroup::orderBy('name')->get(),
            'setores' => Sector::orderBy('name')->get(),
            'lojas' => Store::orderBy('name')->get(),
            'filtros' => ['q' => $busca, 'grupo' => $grupo, 'setor' => $setor, 'loja' => $loja, 'situacao' => $situacao],
            'totais' => [
                'todos' => User::count(),
                'ativos' => User::where('ativo', true)->count(),
                'desativados' => User::where('ativo', false)->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.usuarios.form', ['usuario' => null] + $this->opcoes());
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::default()],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['integer', 'exists:access_groups,id'],
        ] + $this->regrasSetorLoja());

        if ($erro = $this->conferirGrupos(collect($dados['groups'] ?? []))) {
            return $erro;
        }

        $usuario = User::create([
            'name' => $dados['name'],
            'email' => mb_strtolower($dados['email']),
            'password' => Hash::make($dados['password']),
        ]);

        // Contas criadas pelo administrador já nascem com o e-mail confirmado
        $usuario->forceFill([
            'email_verified_at' => now(),
            'ativo' => true,
            'sector_id' => $dados['sector_id'] ?? null,
            'store_id' => $dados['store_id'] ?? null,
        ])->save();

        $usuario->accessGroups()->sync($dados['groups'] ?? []);

        return redirect()->route('admin.usuarios.edit', $usuario)
            ->with('msg', 'Usuário criado. Informe a senha provisória à pessoa e peça para trocá-la no perfil.');
    }

    public function edit(User $usuario)
    {
        return view('admin.usuarios.form', [
            'usuario' => $usuario->load('accessGroups')->loadCount(['tasks', 'tasksAsParticipant', 'equipamentos']),
            'equipamentos' => $usuario->equipamentos()->with('store')->orderBy('name')->limit(20)->get(),
        ] + $this->opcoes());
    }

    public function update(Request $request, User $usuario)
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'password' => ['nullable', 'confirmed', Password::default()],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['integer', 'exists:access_groups,id'],
        ] + $this->regrasSetorLoja());

        if ($erro = $this->protegerAdministrador($usuario)) {
            return $erro;
        }

        $selecionados = collect($dados['groups'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $gruposEscolhidos = AccessGroup::whereIn('id', $selecionados)->get();

        if ($erro = $this->conferirGrupos($selecionados)) {
            return $erro;
        }

        // Trava: o sistema nunca pode ficar sem administrador ativo
        $administrador = $this->grupoAdministrador();

        if ($administrador
            && $usuario->ativo
            && $usuario->accessGroups->contains('id', $administrador->id)
            && ! $selecionados->contains($administrador->id)
            && $this->administradoresAtivos() <= 1) {
            return back()->withInput()->withErrors([
                'groups' => 'Este é o único administrador ativo. Mantenha o grupo Administrador ou promova outra pessoa antes.',
            ]);
        }

        // Trava: ninguém tira, sem querer, o próprio acesso a esta tela
        if ($this->ehVoceMesmo($usuario)
            && ! $gruposEscolhidos->contains(fn (AccessGroup $grupo) => $grupo->grants('usuarios.gerenciar'))) {
            return back()->withInput()->withErrors([
                'groups' => 'Você não pode remover o seu próprio acesso à administração de usuários.',
            ]);
        }

        $emailMudou = mb_strtolower($dados['email']) !== mb_strtolower($usuario->email);

        $usuario->name = $dados['name'];
        $usuario->email = mb_strtolower($dados['email']);
        $usuario->forceFill([
            'sector_id' => $dados['sector_id'] ?? null,
            'store_id' => $dados['store_id'] ?? null,
        ]);

        if (! empty($dados['password'])) {
            $usuario->password = Hash::make($dados['password']);
        }

        $usuario->save();
        $usuario->accessGroups()->sync($selecionados->all());

        $mensagem = 'Usuário atualizado.';

        if (! empty($dados['password']) && ! $this->ehVoceMesmo($usuario)) {
            $mensagem .= ' Informe a nova senha à pessoa.';
        }

        if ($emailMudou) {
            $mensagem .= ' O e-mail de acesso mudou: a pessoa passa a entrar com '.$usuario->email.'.';
        }

        return redirect()->route('admin.usuarios.edit', $usuario)->with('msg', $mensagem);
    }

    // --------------------------------------------------------- Ativar / desativar

    public function desativar(User $usuario)
    {
        if ($erro = $this->protegerAdministrador($usuario)) {
            return $erro;
        }

        if ($this->ehVoceMesmo($usuario)) {
            return back()->withErrors(['conta' => 'Você não pode desativar a sua própria conta.']);
        }

        if ($usuario->isAdministrator() && $this->administradoresAtivos() <= 1) {
            return back()->withErrors(['conta' => 'Este é o único administrador ativo. Promova outra pessoa antes de desativá-lo.']);
        }

        $usuario->forceFill(['ativo' => false, 'remember_token' => null])->save();

        $mensagem = "{$usuario->name} foi desativado. A pessoa não consegue mais entrar e, se estiver logada, será desconectada.";
        $comEle = $usuario->equipamentos()->count();

        if ($comEle > 0) {
            $mensagem .= " Atenção: {$comEle} ".($comEle === 1 ? 'equipamento continua vinculado' : 'equipamentos continuam vinculados').' a esta conta. Transfira-os na tela de equipamentos.';
        }

        return back()->with('msg', $mensagem);
    }

    public function ativar(User $usuario)
    {
        if ($erro = $this->protegerAdministrador($usuario)) {
            return $erro;
        }

        $usuario->forceFill(['ativo' => true])->save();

        return back()->with('msg', "{$usuario->name} foi reativado e já pode entrar novamente.");
    }

    // ------------------------------------------------------------ Ações de conta

    /**
     * Envia para o e-mail da pessoa o link de "redefinir senha" (o mesmo do "Esqueci minha senha").
     */
    public function enviarRedefinicaoDeSenha(User $usuario)
    {
        if ($erro = $this->protegerAdministrador($usuario)) {
            return $erro;
        }

        $status = RedefinirSenha::broker()->sendResetLink(['email' => $usuario->email]);

        if ($status === RedefinirSenha::RESET_LINK_SENT) {
            return back()->with('msg', "Link para redefinir a senha enviado para {$usuario->email}.");
        }

        if ($status === RedefinirSenha::RESET_THROTTLED) {
            return back()->withErrors(['conta' => 'Um link foi enviado há pouco tempo. Aguarde alguns minutos para enviar outro.']);
        }

        return back()->withErrors(['conta' => 'Não foi possível enviar o link. Confira a configuração de e-mail do sistema.']);
    }

    /**
     * Remove a verificação em duas etapas (ex.: a pessoa trocou ou perdeu o celular).
     * Ela entra só com a senha e pode ativar de novo no perfil.
     */
    public function removerDoisFatores(User $usuario)
    {
        if ($erro = $this->protegerAdministrador($usuario)) {
            return $erro;
        }

        $usuario->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('msg', "Verificação em duas etapas removida de {$usuario->name}. A pessoa pode ativá-la de novo no perfil.");
    }

    public function confirmarEmail(User $usuario)
    {
        if ($erro = $this->protegerAdministrador($usuario)) {
            return $erro;
        }

        if (! $usuario->hasVerifiedEmail()) {
            $usuario->markEmailAsVerified();
        }

        return back()->with('msg', "E-mail de {$usuario->name} marcado como confirmado.");
    }

    // ------------------------------------------------------------------ Apoio

    private function opcoes(): array
    {
        return [
            'grupos' => AccessGroup::orderBy('name')->get(),
            'setores' => Sector::orderBy('name')->get(),
            'lojas' => Store::orderBy('name')->get(),
        ];
    }

    /** Setor e empresa são opcionais (um de cada por pessoa). */
    private function regrasSetorLoja(): array
    {
        return [
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ];
    }

    /**
     * Só administradores alteram contas de administradores.
     */
    private function protegerAdministrador(User $alvo): ?RedirectResponse
    {
        if ($alvo->isAdministrator() && ! auth()->user()->isAdministrator()) {
            return back()->withInput()->withErrors([
                'conta' => 'Somente administradores podem alterar a conta de outro administrador.',
            ]);
        }

        return null;
    }

    /**
     * Quem não é administrador só atribui grupos com permissões que ele mesmo tem
     * (e nunca o grupo Administrador).
     */
    private function conferirGrupos($ids): ?RedirectResponse
    {
        $grupos = AccessGroup::whereIn('id', collect($ids)->map(fn ($id) => (int) $id))->get();
        $permissoes = $grupos->contains(fn (AccessGroup $g) => $g->isAdministrator())
            ? Permissoes::chaves()
            : $grupos->flatMap(fn (AccessGroup $g) => $g->permissions ?? [])->unique()->values()->all();

        if (! Permissoes::podeConceder(auth()->user(), $permissoes)) {
            return back()->withInput()->withErrors([
                'groups' => 'Você só pode atribuir grupos com permissões que você mesmo tem. O grupo Administrador só pode ser dado por um administrador.',
            ]);
        }

        return null;
    }

    private function ehVoceMesmo(User $usuario): bool
    {
        return (int) $usuario->id === (int) auth()->id();
    }

    private function grupoAdministrador(): ?AccessGroup
    {
        return AccessGroup::where('slug', 'administrador')->first();
    }

    private function administradoresAtivos(): int
    {
        $administrador = $this->grupoAdministrador();

        return $administrador ? $administrador->users()->where('ativo', true)->count() : 0;
    }
}
