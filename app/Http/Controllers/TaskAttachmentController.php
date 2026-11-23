<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\AnexosTarefa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Anexos da tarefa: abrir, enviar mais arquivos e apagar.
 */
class TaskAttachmentController extends Controller
{
    public function __construct(private AnexosTarefa $anexos) {}

    /**
     * Entrega o arquivo só para quem pode ver a tarefa.
     * Imagem e PDF abrem no navegador; o nome original volta no download.
     */
    public function show($id, TaskAttachment $anexo)
    {
        $tarefa = Task::with(['users', 'participantes'])->findOrFail($id);

        abort_unless((int) $anexo->task_id === (int) $tarefa->id && $tarefa->visivelPara(auth()->user()) && $anexo->existe(), 404);

        $cabecalhos = [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ];

        // Imagens abrem em "caixa fechada" (sem scripts). O PDF não, porque o leitor de PDF do navegador não funciona assim.
        if ($anexo->ehImagem()) {
            $cabecalhos['Content-Security-Policy'] = "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox";
        }

        return Storage::disk('local')->response($anexo->caminho(), $anexo->nome_original, $cabecalhos, 'inline');
    }

    public function store(Request $request, $id)
    {
        $tarefa = Task::with(['users', 'participantes'])->findOrFail($id);

        abort_unless($tarefa->podeAnexar($request->user()), 403);

        // Mesmas regras do formulário da tarefa, mas aqui é obrigatório mandar pelo menos um arquivo
        $regras = AnexosTarefa::regras($tarefa);
        $regras['anexos'] = array_merge(['required'], array_diff($regras['anexos'], ['nullable']));

        $request->validate($regras, ['anexos.required' => 'Escolha pelo menos um arquivo.'] + AnexosTarefa::mensagens());

        $quantos = $this->anexos->salvar($tarefa, $request->file('anexos', []), $request->user());

        if ($quantos > 0) {
            $tarefa->registrar('anexo', $request->user(), null, ['quantidade' => $quantos]);
        }

        return redirect()->to('/tasks/'.$tarefa->id.'#anexos')
            ->with('msg', $quantos === 1 ? 'Arquivo anexado.' : "{$quantos} arquivos anexados.");
    }

    public function destroy(Request $request, $id, TaskAttachment $anexo)
    {
        $tarefa = Task::findOrFail($id);

        abort_unless((int) $anexo->task_id === (int) $tarefa->id && $anexo->podeApagar($request->user(), $tarefa), 403);

        Storage::disk('local')->delete($anexo->caminho());
        $anexo->delete();

        $tarefa->registrar('anexo_removido', $request->user(), null, ['nome' => $anexo->nome_original]);

        return redirect()->to('/tasks/'.$tarefa->id.'#anexos')->with('msg', 'Anexo removido.');
    }
}
