<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $usuarios = User::with('accessGroups')->orderBy('name')->get();

        return view('admin.usuarios.index', ['usuarios' => $usuarios]);
    }

    public function create()
    {
        return view('admin.usuarios.form', [
            'usuario' => null,
            'grupos' => AccessGroup::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::default()],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['integer', 'exists:access_groups,id'],
        ]);

        $usuario = User::create([
            'name' => $dados['name'],
            'email' => $dados['email'],
            'password' => Hash::make($dados['password']),
        ]);

        // Contas criadas pelo administrador já nascem com o e-mail confirmado
        $usuario->forceFill(['email_verified_at' => now()])->save();

        $usuario->accessGroups()->sync($dados['groups'] ?? []);

        return redirect()->route('admin.usuarios.index')
            ->with('msg', 'Usuário criado. Informe a senha provisória à pessoa e peça para trocá-la no perfil.');
    }

    public function edit(User $usuario)
    {
        return view('admin.usuarios.form', [
            'usuario' => $usuario->load('accessGroups'),
            'grupos' => AccessGroup::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $usuario)
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'password' => ['nullable', 'confirmed', Password::default()],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['integer', 'exists:access_groups,id'],
        ]);

        $selecionados = collect($dados['groups'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $gruposEscolhidos = AccessGroup::whereIn('id', $selecionados)->get();

        // Trava de segurança: o sistema nunca pode ficar sem administrador
        $administrador = AccessGroup::where('slug', 'administrador')->first();

        if ($administrador
            && $usuario->accessGroups->contains('id', $administrador->id)
            && ! $selecionados->contains($administrador->id)
            && $administrador->users()->count() <= 1) {
            return back()->withInput()->withErrors([
                'groups' => 'Este é o único administrador. Mantenha o grupo Administrador ou promova outra pessoa antes.',
            ]);
        }

        // Ninguém pode tirar, sem querer, o próprio acesso a esta tela
        if ((int) $usuario->id === (int) auth()->id()
            && ! $gruposEscolhidos->contains(fn (AccessGroup $grupo) => $grupo->grants('usuarios.gerenciar'))) {
            return back()->withInput()->withErrors([
                'groups' => 'Você não pode remover o seu próprio acesso à administração de usuários.',
            ]);
        }

        $usuario->name = $dados['name'];
        $usuario->email = $dados['email'];

        if (! empty($dados['password'])) {
            $usuario->password = Hash::make($dados['password']);
        }

        $usuario->save();
        $usuario->accessGroups()->sync($selecionados->all());

        return redirect()->route('admin.usuarios.index')->with('msg', 'Usuário atualizado.');
    }
}
