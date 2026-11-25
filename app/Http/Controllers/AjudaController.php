<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use App\Models\HelpAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Central de Ajuda para quem usa o sistema: lista, leitura, "Li e estou ciente" e arquivos.
 * Cada pessoa só enxerga o que foi publicado para o setor e a empresa dela.
 */
class AjudaController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->user();
        $tipo = array_key_exists($request->query('tipo'), HelpArticle::TIPOS) ? $request->query('tipo') : null;
        $busca = trim((string) $request->query('q', ''));
        $categoria = trim((string) $request->query('categoria', ''));

        // Base: tudo o que a pessoa pode ver (por setor e empresa)
        $visiveis = fn () => HelpArticle::query()->visivelPara($usuario);

        $artigos = $visiveis()
            ->with(['setores', 'lojas'])
            ->withCount('anexos')
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo))
            ->when($categoria !== '', fn ($q) => $q->where('categoria', $categoria))
            ->when($busca !== '', function ($q) use ($busca) {
                $termo = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $busca).'%';
                $q->where(fn ($w) => $w->where('titulo', 'like', $termo)
                    ->orWhere('resumo', 'like', $termo)
                    ->orWhere('conteudo', 'like', $termo)
                    ->orWhere('categoria', 'like', $termo));
            })
            ->orderByDesc('fixado')
            ->orderByDesc('publicado_em')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Números das abas (quantos de cada tipo a pessoa pode ver)
        $contagem = $visiveis()->selectRaw('tipo, count(*) as total')->groupBy('tipo')->pluck('total', 'tipo');

        $categorias = $visiveis()
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo))
            ->whereNotNull('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');

        // Ids das circulares que a pessoa já confirmou, para marcar "Lida" na lista
        $lidas = $usuario->id ? \App\Models\HelpRead::where('user_id', $usuario->id)->pluck('help_article_id')->all() : [];

        $pendentes = HelpArticle::query()->visivelPara($usuario)->pendentesDeCiencia($usuario)
            ->orderByDesc('publicado_em')->get(['id', 'titulo', 'publicado_em']);

        return view('ajuda.index', compact('artigos', 'tipo', 'busca', 'categoria', 'categorias', 'contagem', 'lidas', 'pendentes'));
    }

    public function show(Request $request, HelpArticle $artigo)
    {
        // 404 (e não 403) para não revelar que o conteúdo existe para outro setor
        abort_unless($artigo->estaVisivelPara($request->user()), 404);

        $artigo->load(['setores', 'lojas', 'anexos', 'autor', 'editor']);

        return view('ajuda.show', [
            'artigo' => $artigo,
            'leitura' => $artigo->lidoPor($request->user()),
        ]);
    }

    /** "Li e estou ciente" de uma circular */
    public function ciente(Request $request, HelpArticle $artigo)
    {
        abort_unless($artigo->estaVisivelPara($request->user()) && $artigo->publicado, 404);
        abort_unless($artigo->tipo === 'circular' && $artigo->exige_ciencia, 422);

        // firstOrCreate: clicar duas vezes não cria duas confirmações
        $artigo->leituras()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['lido_em' => now(), 'ip' => $request->ip()]
        );

        return redirect()->route('ajuda.show', $artigo)->with('msg', 'Leitura confirmada. Obrigado!');
    }

    /** Entrega o arquivo só para quem pode ver o conteúdo */
    public function anexo(Request $request, HelpArticle $artigo, HelpAttachment $anexo)
    {
        abort_unless(
            (int) $anexo->help_article_id === (int) $artigo->id
                && $artigo->estaVisivelPara($request->user())
                && $anexo->existe(),
            404
        );

        $cabecalhos = [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ];

        if ($anexo->ehImagem()) {
            $cabecalhos['Content-Security-Policy'] = "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox";
        }

        // PDF, imagem e vídeo abrem no navegador; Word/Excel/PowerPoint são baixados com o nome original
        $modo = $anexo->abreNoNavegador() && ! $request->boolean('baixar') ? 'inline' : 'attachment';
        $cabecalhos['Content-Type'] = $anexo->mime;

        // response()->file() entrega o arquivo "em pedaços" quando o navegador pede (cabeçalho Range,
        // resposta 206). É isso que permite pular para o meio de um vídeo sem baixar tudo antes.
        $resposta = response()->file(Storage::disk('local')->path($anexo->caminho()), $cabecalhos);

        // Nome sem acentos como reserva para navegadores antigos; o nome original vai junto
        $reserva = preg_replace('/[^A-Za-z0-9._-]+/', '_', Str::ascii($anexo->nome_original)) ?: 'arquivo';
        $resposta->setContentDisposition($modo, $anexo->nome_original, $reserva);

        return $resposta;
    }
}
