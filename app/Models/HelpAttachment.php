<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Arquivo de um conteúdo da Central de Ajuda (PDF, Word, Excel, PowerPoint, imagem ou vídeo),
 * guardado em storage/app/private/help-attachments: fora da pasta public, então só
 * abre para quem pode ver o conteúdo.
 */
class HelpAttachment extends Model
{
    public const PASTA = 'help-attachments';

    public const TAMANHO_KB = 20480; // 20 MB por arquivo (documentos e imagens)

    /** Vídeos podem ser maiores. Vídeo é aceito SÓ na Central de Ajuda (os anexos de tarefa têm regras próprias). */
    public const VIDEO_KB = 204800; // 200 MB por vídeo

    public const VIDEO_EXTENSOES = ['mp4', 'm4v', 'webm', 'mov'];

    public const POR_ENVIO = 10;

    /** Extensões aceitas. "mimes" na validação confere o conteúdo, não só o nome. */
    public const EXTENSOES = 'pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,webp,mp4,m4v,webm,mov';

    protected $fillable = ['help_article_id', 'user_id', 'nome_original', 'arquivo', 'mime', 'tamanho'];

    public function artigo(): BelongsTo
    {
        return $this->belongsTo(HelpArticle::class, 'help_article_id');
    }

    public static function regras(): array
    {
        return [
            'anexos' => ['nullable', 'array', 'max:'.self::POR_ENVIO],
            'anexos.*' => [
                'file',
                'mimes:'.self::EXTENSOES,
                'max:'.self::VIDEO_KB, // teto geral (o dos vídeos)...
                // ...e um limite menor para o que NÃO é vídeo. Uma "closure" é uma regra
                // escrita na hora: recebe o arquivo e chama $falhar() se ele não passar.
                function (string $campo, $arquivo, \Closure $falhar) {
                    if ($arquivo instanceof UploadedFile
                        && ! self::arquivoEhVideo($arquivo)
                        && $arquivo->getSize() > self::TAMANHO_KB * 1024) {
                        $falhar('Documentos e imagens podem ter no máximo 20 MB (vídeos, até 200 MB).');
                    }
                },
            ],
        ];
    }

    /** O arquivo enviado é um vídeo? Confere o tipo real do conteúdo, não só o nome. */
    public static function arquivoEhVideo(UploadedFile $arquivo): bool
    {
        return str_starts_with((string) $arquivo->getMimeType(), 'video/')
            && in_array(strtolower((string) $arquivo->guessExtension()), self::VIDEO_EXTENSOES, true);
    }

    public static function mensagens(): array
    {
        return [
            'anexos.max' => 'Envie no máximo '.self::POR_ENVIO.' arquivos por vez.',
            'anexos.*.mimes' => 'Os arquivos devem ser PDF, Word, Excel, PowerPoint, imagens (JPG, PNG, WEBP) ou vídeos (MP4, WEBM, MOV).',
            'anexos.*.max' => 'Cada vídeo pode ter no máximo 200 MB (documentos e imagens, 20 MB).',
            'anexos.*.uploaded' => 'Não foi possível enviar um dos arquivos. Ele pode ser maior que o limite do servidor.',
        ];
    }

    /**
     * Guarda os arquivos enviados no conteúdo.
     *
     * @param  array<int, UploadedFile>  $arquivos
     */
    public static function salvar(HelpArticle $artigo, array $arquivos, ?User $autor): int
    {
        $guardados = 0;

        foreach ($arquivos as $arquivo) {
            if (! $arquivo instanceof UploadedFile || ! $arquivo->isValid()) {
                continue;
            }

            // Lê tudo o que precisamos ANTES de mover: depois do move() o arquivo temporário deixa de existir
            $dados = [
                'user_id' => $autor?->id,
                'nome_original' => mb_substr($arquivo->getClientOriginalName(), 0, 255),
                'mime' => $arquivo->getMimeType() ?: 'application/octet-stream',
                'tamanho' => (int) $arquivo->getSize(),
            ];

            // Nome aleatório (ninguém adivinha o endereço do arquivo) + extensão pelo conteúdo real
            $nome = Str::random(40).'.'.($arquivo->guessExtension() ?: 'bin');

            // move() usa o caminho do arquivo temporário do jeito que o PHP o entregou.
            // O store() usado antes dependia do "caminho real" (realpath) do temporário, que em
            // algumas instalações do Windows/Laragon volta vazio e causava "Path must not be empty".
            $arquivo->move(Storage::disk('local')->path(self::PASTA), $nome);

            $artigo->anexos()->create($dados + ['arquivo' => $nome]);

            $guardados++;
        }

        return $guardados;
    }

    public function caminho(): string
    {
        return self::PASTA.'/'.$this->arquivo;
    }

    public function existe(): bool
    {
        return Storage::disk('local')->exists($this->caminho());
    }

    public function ehImagem(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function ehVideo(): bool
    {
        return str_starts_with($this->mime, 'video/');
    }

    /** PDF, imagens e vídeos abrem no navegador; Word, Excel e PowerPoint são baixados */
    public function abreNoNavegador(): bool
    {
        return $this->ehImagem() || $this->ehVideo() || $this->mime === 'application/pdf';
    }

    public function icone(): string
    {
        return match (true) {
            $this->ehImagem() => 'image-outline',
            $this->ehVideo() => 'videocam-outline',
            $this->mime === 'application/pdf' => 'document-text-outline',
            str_contains($this->mime, 'sheet') || str_contains($this->mime, 'excel') => 'grid-outline',
            str_contains($this->mime, 'presentation') || str_contains($this->mime, 'powerpoint') => 'easel-outline',
            default => 'document-outline',
        };
    }

    public function tamanhoLegivel(): string
    {
        if ($this->tamanho >= 1024 * 1024) {
            return number_format($this->tamanho / 1024 / 1024, 1, ',', '.').' MB';
        }

        return max(1, (int) round($this->tamanho / 1024)).' KB';
    }

    /** Ao apagar o registro, apaga também o arquivo do disco */
    protected static function booted(): void
    {
        static::deleting(function (HelpAttachment $anexo) {
            Storage::disk('local')->delete($anexo->caminho());
        });
    }
}
