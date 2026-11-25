<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Models\HelpAttachment;
use App\Models\Sector;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Publicação na Central de Ajuda (permissão "faq.gerenciar", conferida nas rotas):
 * criar, editar, excluir, apagar arquivos e ver quem já leu cada circular.
 */
class AjudaGerenciarController extends Controller
{
    public function create(Request $request)
    {
        $tipo = array_key_exists($request->query('tipo'), HelpArticle::TIPOS) ? $request->query('tipo') : 'faq';

        return view('ajuda.form', $this->dadosDoFormulario(null, $tipo));
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);

        // transaction(): ou salva TUDO (conteúdo, público e arquivos) ou não salva nada.
        // Assim, se um arquivo falhar, não fica um conteúdo pela metade publicado.
        $artigo = DB::transaction(function () use ($dados, $request) {
            $artigo = new HelpArticle($this->campos($dados, $request));
            $artigo->user_id = $request->user()->id;
            $artigo->atualizado_por = $request->user()->id;
            $artigo->publicado_em = $artigo->publicado ? now() : null;
            $artigo->save();

            $this->salvarPublicoEArquivos($artigo, $dados, $request);

            return $artigo;
        });

        return redirect()->route('ajuda.show', $artigo)
            ->with('msg', $artigo->publicado ? 'Conteúdo publicado.' : 'Rascunho salvo. Só quem gerencia a Central de Ajuda consegue ver.');
    }

    public function edit(HelpArticle $artigo)
    {
        $artigo->load(['setores', 'lojas', 'anexos']);

        return view('ajuda.form', $this->dadosDoFormulario($artigo, $artigo->tipo));
    }

    public function update(Request $request, HelpArticle $artigo)
    {
        $dados = $this->validar($request);
        $estavaPublicado = $artigo->publicado;

        $artigo->fill($this->campos($dados, $request));
        $artigo->atualizado_por = $request->user()->id;

        // A data de publicação é a da PRIMEIRA vez que o conteúdo foi publicado
        if ($artigo->publicado && ! $estavaPublicado && ! $artigo->publicado_em) {
            $artigo->publicado_em = now();
        }

        DB::transaction(function () use ($artigo, $dados, $request) {
            $artigo->save();
            $this->salvarPublicoEArquivos($artigo, $dados, $request);
        });

        // Mudou o texto de uma circular e quer que todos confirmem de novo?
        if ($artigo->tipo === 'circular' && $request->boolean('pedir_ciencia_novamente')) {
            $artigo->leituras()->delete();
        }

        return redirect()->route('ajuda.show', $artigo)->with('msg', 'Alterações salvas.');
    }

    public function destroy(HelpArticle $artigo)
    {
        // Apaga um a um para o evento "deleting" de cada anexo apagar o arquivo do disco
        $artigo->anexos->each->delete();
        $artigo->delete();

        return redirect()->route('ajuda.index', ['tipo' => $artigo->tipo])->with('msg', 'Conteúdo excluído.');
    }

    public function apagarAnexo(HelpArticle $artigo, HelpAttachment $anexo)
    {
        abort_unless((int) $anexo->help_article_id === (int) $artigo->id, 404);

        $anexo->delete();

        return redirect()->route('ajuda.edit', $artigo)->with('msg', 'Arquivo removido.');
    }

    /** Relatório da circular: quem já confirmou a leitura e quem ainda falta */
    public function leituras(Request $request, HelpArticle $artigo)
    {
        abort_unless($artigo->tipo === 'circular', 404);

        $artigo->load(['setores', 'lojas']);

        $leituras = $artigo->leituras()->get()->keyBy('user_id');

        $pessoas = $artigo->publicoAlvo()
            ->with(['sector', 'store'])
            ->orderBy('name')
            ->get()
            ->map(fn ($pessoa) => (object) [
                'pessoa' => $pessoa,
                'leitura' => $leituras->get($pessoa->id),
            ]);

        // Quem confirmou mas não faz mais parte do público (mudou de setor, por exemplo) continua no relatório
        $foraDoPublico = $leituras->except($pessoas->pluck('pessoa.id')->all());

        $filtro = $request->query('filtro');
        $lista = match ($filtro) {
            'lidas' => $pessoas->filter(fn ($l) => $l->leitura),
            'pendentes' => $pessoas->reject(fn ($l) => $l->leitura),
            default => $pessoas,
        };

        return view('ajuda.leituras', [
            'artigo' => $artigo,
            'lista' => $lista,
            'total' => $pessoas->count(),
            'lidas' => $pessoas->filter(fn ($l) => $l->leitura)->count(),
            'foraDoPublico' => $foraDoPublico->load('user'),
            'filtro' => $filtro,
        ]);
    }

    // ------------------------------------------------------------------ Ajudantes

    private function validar(Request $request): array
    {
        return $request->validate([
            'tipo' => ['required', Rule::in(array_keys(HelpArticle::TIPOS))],
            'titulo' => ['required', 'string', 'max:255'],
            'resumo' => ['nullable', 'string', 'max:300'],
            'conteudo' => ['nullable', 'string', 'max:100000'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'setores' => ['nullable', 'array'],
            'setores.*' => ['integer', 'exists:sectors,id'],
            'lojas' => ['nullable', 'array'],
            'lojas.*' => ['integer', 'exists:stores,id'],
        ] + HelpAttachment::regras(), [
            'titulo.required' => 'Informe o título.',
        ] + HelpAttachment::mensagens());
    }

    /** Campos simples do conteúdo. Caixas de seleção desmarcadas não chegam no formulário, por isso boolean(). */
    private function campos(array $dados, Request $request): array
    {
        return [
            'tipo' => $dados['tipo'],
            'titulo' => $dados['titulo'],
            'resumo' => $dados['resumo'] ?? null,
            'conteudo' => $dados['conteudo'] ?? null,
            'categoria' => isset($dados['categoria']) ? trim($dados['categoria']) ?: null : null,
            // Só circular pode pedir ciência
            'exige_ciencia' => $dados['tipo'] === 'circular' && $request->boolean('exige_ciencia'),
            'fixado' => $request->boolean('fixado'),
            'publicado' => $request->boolean('publicado'),
        ];
    }

    private function salvarPublicoEArquivos(HelpArticle $artigo, array $dados, Request $request): void
    {
        // sync(): deixa marcados exatamente os setores/lojas enviados (vazio = todos)
        $artigo->setores()->sync($dados['setores'] ?? []);
        $artigo->lojas()->sync($dados['lojas'] ?? []);

        HelpAttachment::salvar($artigo, $request->file('anexos', []), $request->user());
    }

    private function dadosDoFormulario(?HelpArticle $artigo, string $tipo): array
    {
        return [
            'artigo' => $artigo,
            'tipo' => $tipo,
            'setores' => Sector::orderBy('name')->get(),
            'lojas' => Store::orderBy('name')->get(),
            'categorias' => HelpArticle::whereNotNull('categoria')->distinct()->orderBy('categoria')->pluck('categoria'),
        ];
    }
}
