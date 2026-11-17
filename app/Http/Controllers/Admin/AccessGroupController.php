<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessGroup;
use App\Support\Permissoes;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccessGroupController extends Controller
{
    public function index()
    {
        $grupos = AccessGroup::withCount('users')->orderBy('name')->get();

        return view('admin.grupos.index', ['grupos' => $grupos]);
    }

    public function create()
    {
        return view('admin.grupos.form', ['grupo' => null]);
    }

    public function store(Request $request)
    {
        $request->merge(['slug' => Str::slug((string) $request->input('name'))]);

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:access_groups,name'],
            'slug' => ['unique:access_groups,slug'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permissoes::chaves())],
        ], [
            'slug.unique' => 'Já existe um grupo com um nome muito parecido.',
        ]);

        if (! Permissoes::podeConceder($request->user(), $dados['permissions'] ?? [])) {
            return back()->withInput()->withErrors([
                'permissions' => 'Você só pode conceder permissões que você mesmo tem. Peça a um administrador.',
            ]);
        }

        AccessGroup::create([
            'name' => $dados['name'],
            'slug' => $dados['slug'],
            'description' => $dados['description'] ?? null,
            'permissions' => array_values($dados['permissions'] ?? []),
        ]);

        return redirect()->route('admin.grupos.index')->with('msg', 'Grupo criado.');
    }

    public function edit(AccessGroup $grupo)
    {
        return view('admin.grupos.form', ['grupo' => $grupo]);
    }

    public function update(Request $request, AccessGroup $grupo)
    {
        // Só administradores alteram o grupo Administrador ou grupos com permissões que a pessoa não tem
        if (! Permissoes::podeConceder($request->user(), $grupo->isAdministrator() ? Permissoes::chaves() : ($grupo->permissions ?? []))) {
            return back()->withErrors([
                'permissions' => 'Este grupo tem permissões que você não tem. Somente um administrador pode alterá-lo.',
            ]);
        }

        // O grupo Administrador sempre tem todas as permissões: só a descrição pode mudar
        if ($grupo->isAdministrator()) {
            $dados = $request->validate([
                'description' => ['nullable', 'string', 'max:255'],
            ]);

            $grupo->update(['description' => $dados['description'] ?? null]);

            return redirect()->route('admin.grupos.index')->with('msg', 'Grupo atualizado.');
        }

        $request->merge(['slug' => Str::slug((string) $request->input('name'))]);

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('access_groups', 'name')->ignore($grupo->id)],
            'slug' => [Rule::unique('access_groups', 'slug')->ignore($grupo->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permissoes::chaves())],
        ], [
            'slug.unique' => 'Já existe um grupo com um nome muito parecido.',
        ]);

        if (! Permissoes::podeConceder($request->user(), $dados['permissions'] ?? [])) {
            return back()->withInput()->withErrors([
                'permissions' => 'Você só pode conceder permissões que você mesmo tem. Peça a um administrador.',
            ]);
        }

        // O slug original é mantido: ele identifica o grupo no sistema
        // (por exemplo, o grupo padrão do cadastro).
        $grupo->update([
            'name' => $dados['name'],
            'description' => $dados['description'] ?? null,
            'permissions' => array_values($dados['permissions'] ?? []),
        ]);

        return redirect()->route('admin.grupos.index')->with('msg', 'Grupo atualizado.');
    }

    public function destroy(Request $request, AccessGroup $grupo)
    {
        if (! Permissoes::podeConceder($request->user(), $grupo->permissions ?? [])) {
            return back()->with('msg', 'Este grupo tem permissões que você não tem. Somente um administrador pode excluí-lo.');
        }

        if ($grupo->is_system) {
            return back()->with('msg', 'Este grupo é do sistema e não pode ser excluído.');
        }

        $grupo->users()->detach();
        $grupo->delete();

        return redirect()->route('admin.grupos.index')->with('msg', 'Grupo excluído.');
    }
}
