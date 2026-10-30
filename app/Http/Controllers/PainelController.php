<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Store;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

class PainelController extends Controller
{
    public function index(Request $request)
    {
        $dias = in_array((int) $request->query('dias'), [7, 14, 30], true) ? (int) $request->query('dias') : 14;
        $hoje = today();
        $limite = $hoje->copy()->addDays(7);
        $inicio = $hoje->copy()->subDays($dias - 1);

        // O volume de tarefas é pequeno, então os números são calculados a partir da lista carregada.
        $tarefas = Task::with(['equipment.store', 'users'])->get();

        $kpis = [
            'total' => $tarefas->count(),
            'urgentes' => $tarefas->filter(fn (Task $t) => (bool) $t->priority)->count(),
            'vencidas' => $tarefas->filter(fn (Task $t) => $t->deadline->lt($hoje))->count(),
            'vencem7' => $tarefas->filter(fn (Task $t) => $t->deadline->gte($hoje) && $t->deadline->lte($limite))->count(),
            // Ninguém além de quem abriu a tarefa assumiu
            'sem_responsavel' => $tarefas->filter(
                fn (Task $t) => $t->users->reject(fn ($u) => (int) $u->id === (int) $t->user_id)->isEmpty()
            )->count(),
            'no_periodo' => $tarefas->filter(fn (Task $t) => $t->created_at->gte($inicio))->count(),
        ];

        // Tarefas abertas por dia
        $porDia = $tarefas->groupBy(fn (Task $t) => $t->created_at->toDateString())->map->count();
        $serie = [];

        for ($dia = $inicio->copy(); $dia->lte($hoje); $dia->addDay()) {
            $serie[] = [
                'curto' => $dia->format('d/m'),
                'rotulo' => $dia->format('d/m/Y'),
                'qtd' => (int) ($porDia[$dia->toDateString()] ?? 0),
            ];
        }

        // Por loja (a loja vem do equipamento ligado à tarefa)
        $porLoja = $tarefas
            ->groupBy(function (Task $t) {
                if (! $t->equipment) {
                    return 'Sem equipamento';
                }

                return $t->equipment->store->name ?? 'Equipamento sem loja';
            })
            ->map->count()
            ->sortDesc()
            ->map(fn ($qtd, $nome) => ['rotulo' => $nome, 'valor' => $qtd, 'apagado' => $nome === 'Sem equipamento'])
            ->values()
            ->all();

        // Por tipo de equipamento
        $porTipo = $tarefas
            ->filter(fn (Task $t) => $t->equipment)
            ->groupBy(fn (Task $t) => $t->equipment->type)
            ->map->count()
            ->sortDesc()
            ->map(fn ($qtd, $tipo) => ['rotulo' => $tipo, 'valor' => $qtd])
            ->values()
            ->all();

        // Equipamentos com mais tarefas
        $topEquipamentos = $tarefas
            ->filter(fn (Task $t) => $t->equipment)
            ->groupBy('equipment_id')
            ->map(fn ($grupo) => ['equipamento' => $grupo->first()->equipment, 'qtd' => $grupo->count()])
            ->sortByDesc('qtd')
            ->take(5)
            ->values();

        // Carga por técnico: quem pode assumir tarefas e em quantas está como responsável
        $tecnicos = User::with('accessGroups')->get()
            ->filter(fn (User $u) => $u->can('tarefas.assumir'))
            ->pluck('id')
            ->all();

        $carga = $tarefas
            ->flatMap(fn (Task $t) => $t->users->unique('id')->filter(fn ($u) => in_array($u->id, $tecnicos, true)))
            ->groupBy('id')
            ->map(fn ($grupo) => ['rotulo' => $grupo->first()->name, 'valor' => $grupo->count()])
            ->sortByDesc('valor')
            ->take(8)
            ->values()
            ->all();

        // Vencidas e que vencem nos próximos 7 dias
        $vencimentos = $tarefas
            ->filter(fn (Task $t) => $t->deadline->lte($limite))
            ->sortBy('deadline')
            ->take(8)
            ->values();

        // Inventário (somente para quem pode ver os equipamentos)
        $inventario = null;

        if ($request->user()->can('equipamentos.ver')) {
            $inventario = [
                'total' => Equipment::count(),
                'informatica' => Equipment::whereIn('type', config('equipamentos.tipos_informatica'))->count(),
                'sem_patrimonio' => Equipment::whereNull('asset_tag')->count(),
                'sem_loja' => Equipment::whereNull('store_id')->count(),
                'lojas' => Store::withCount('equipment')
                    ->orderByDesc('equipment_count')
                    ->get()
                    ->map(fn (Store $loja) => ['rotulo' => $loja->name, 'valor' => $loja->equipment_count])
                    ->all(),
            ];
        }

        return view('painel.index', compact(
            'dias', 'hoje', 'kpis', 'serie', 'porLoja', 'porTipo', 'topEquipamentos', 'carga', 'vencimentos', 'inventario'
        ));
    }
}
