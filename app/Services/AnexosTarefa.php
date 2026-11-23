<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Guarda os anexos das tarefas (imagens e PDF) numa pasta privada,
 * fora de public/: só quem pode ver a tarefa consegue abrir os arquivos.
 */
class AnexosTarefa
{
    public const POR_ENVIO = 10;      // arquivos de uma vez

    public const POR_TAREFA = 20;     // arquivos no total em uma tarefa

    public const TAMANHO_KB = 10240;  // 10 MB por arquivo

    /** Regras de validação do campo anexos[] */
    public static function regras(?Task $tarefa = null): array
    {
        $jaTem = $tarefa ? $tarefa->anexos()->count() : 0;
        $restam = max(0, self::POR_TAREFA - $jaTem);

        return [
            'anexos' => ['nullable', 'array', 'max:'.min(self::POR_ENVIO, $restam)],
            // "mimes" confere o conteúdo do arquivo, não só a extensão do nome
            'anexos.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.self::TAMANHO_KB],
        ];
    }

    public static function mensagens(): array
    {
        return [
            'anexos.max' => 'Envie no máximo '.self::POR_ENVIO.' arquivos por vez (e até '.self::POR_TAREFA.' por tarefa).',
            'anexos.*.file' => 'Um dos arquivos não pôde ser enviado.',
            'anexos.*.mimes' => 'Os anexos devem ser imagens (JPG, PNG, WEBP) ou PDF.',
            'anexos.*.max' => 'Cada arquivo pode ter no máximo 10 MB.',
            'anexos.*.uploaded' => 'Não foi possível enviar um dos arquivos. Ele pode ser maior que o limite do servidor.',
        ];
    }

    /**
     * @param  array<int, UploadedFile>  $arquivos
     * @return int quantos arquivos foram guardados
     */
    public function salvar(Task $tarefa, array $arquivos, ?User $autor): int
    {
        $guardados = 0;

        foreach ($arquivos as $arquivo) {
            if (! $arquivo instanceof UploadedFile || ! $arquivo->isValid()) {
                continue;
            }

            // Nome aleatório e extensão pelo conteúdo do arquivo (já validado)
            $caminho = $arquivo->store(TaskAttachment::PASTA, 'local');

            $tarefa->anexos()->create([
                'user_id' => $autor?->id,
                'nome_original' => mb_substr($arquivo->getClientOriginalName(), 0, 255),
                'arquivo' => basename($caminho),
                'mime' => $arquivo->getMimeType() ?: 'application/octet-stream',
                'tamanho' => (int) $arquivo->getSize(),
            ]);

            $guardados++;
        }

        return $guardados;
    }
}
