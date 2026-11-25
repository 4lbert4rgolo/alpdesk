<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use App\Models\Task;
use Illuminate\Http\Request;

/**
 * Tela Início (Home): comunicados, circulares pendentes, resumo das minhas tarefas,
 * atalhos e tutoriais recentes. A lista completa de tarefas fica em /tarefas.
 */
class HomeController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->user();

        // Só o que foi publicado para o setor e a loja da pessoa (mesma regra da Central de Ajuda).
        // where('publicado', true): na Home, até quem gerencia vê só o que já foi publicado (sem rascunhos).
        $publicados = fn () => HelpArticle::query()->visivelPara($usuario)->where('publicado', true);

        $comunicados = $publicados()
            ->where('tipo', 'comunicado')
            ->withCount('anexos')
            ->orderByDesc('fixado')
            ->orderByDesc('publicado_em')
            ->limit(5)
            ->get();

        $circularesPendentes = $publicados()
            ->pendentesDeCiencia($usuario)
            ->orderByDesc('publicado_em')
            ->get(['id', 'titulo', 'publicado_em']);

        $tutoriais = $publicados()
            ->whereIn('tipo', ['tutorial', 'documento'])
            ->orderByDesc('publicado_em')
            ->limit(4)
            ->get();

        return view('home', [
            'usuario' => $usuario,
            'saudacao' => $this->saudacao(),
            'comunicados' => $comunicados,
            'circularesPendentes' => $circularesPendentes,
            'tutoriais' => $tutoriais,
            'tarefas' => $this->resumoDasTarefas($usuario),
        ]);
    }

    /**
     * "Minhas tarefas" = as que a pessoa abriu, as em que é responsável e as em que participa
     * (a mesma regra da tela Minhas Tarefas). Aqui entram só as que ainda estão em aberto.
     */
    private function resumoDasTarefas($usuario): array
    {
        // pluck('tasks.id'): pega só os números das tarefas, sem carregar tudo
        $ids = collect()
            ->merge($usuario->tasks()->pluck('id'))
            ->merge($usuario->tasksAsParticipant()->pluck('tasks.id'))
            ->merge($usuario->participacoes()->pluck('tasks.id'))
            ->unique();

        $abertas = Task::query()->whereIn('id', $ids)->emAberto();

        return [
            'total' => (clone $abertas)->count(),
            'aguardando' => (clone $abertas)->where('status', 'aguardando')->count(),
            'atrasadas' => (clone $abertas)->whereNotNull('deadline')->whereDate('deadline', '<', today())->count(),
            'responsavel' => (clone $abertas)->whereHas('users', fn ($q) => $q->whereKey($usuario->id))->count(),
            'recentes' => (clone $abertas)->orderByDesc('updated_at')->limit(5)->get(),
        ];
    }

    private function saudacao(): string
    {
        $hora = (int) now()->format('H');

        return match (true) {
            $hora < 12 => 'Bom dia',
            $hora < 18 => 'Boa tarde',
            default => 'Boa noite',
        };
    }
}
