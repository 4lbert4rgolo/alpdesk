<?php

namespace App\Console\Commands;

use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigrarImagens extends Command
{
    protected $signature = 'alpdesk:migrar-imagens {--simular : Mostra o que seria feito sem mover nada}';

    protected $description = 'Move as imagens antigas de public/img/tasks para a pasta privada (storage/app/private/task-images)';

    /** Somente estes tipos de imagem são aceitos. */
    private const TIPOS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');
        $origem = public_path('img/tasks');
        $disco = Storage::disk('local');

        $resumo = ['movidas' => 0, 'ja_privadas' => 0, 'nao_encontradas' => 0, 'quarentena' => 0];
        $usados = [];

        $tarefas = Task::whereNotNull('image')->where('image', '!=', '')->get();

        foreach ($tarefas as $tarefa) {
            $nome = basename($tarefa->image);

            if ($disco->exists('task-images/'.$nome)) {
                $resumo['ja_privadas']++;

                continue;
            }

            $arquivo = $origem.DIRECTORY_SEPARATOR.$nome;

            if (! is_file($arquivo)) {
                $this->warn("Tarefa #{$tarefa->id}: o arquivo {$nome} não foi encontrado em public/img/tasks.");
                $resumo['nao_encontradas']++;

                continue;
            }

            $usados[] = $nome;
            $tipo = mime_content_type($arquivo) ?: 'desconhecido';
            $ehImagem = isset(self::TIPOS[$tipo]) && @getimagesize($arquivo) !== false;

            if (! $ehImagem) {
                $this->error("Tarefa #{$tarefa->id}: {$nome} NÃO é uma imagem válida (tipo: {$tipo}). Vai para a quarentena.");

                if (! $simular) {
                    $disco->put('quarentena/'.$nome, file_get_contents($arquivo));
                    unlink($arquivo);
                    $tarefa->update(['image' => null]);
                }

                $resumo['quarentena']++;

                continue;
            }

            $novoNome = Str::random(40).'.'.self::TIPOS[$tipo];
            $this->line("Tarefa #{$tarefa->id}: {$nome} -> {$novoNome}");

            if (! $simular) {
                $disco->put('task-images/'.$novoNome, file_get_contents($arquivo));
                $tarefa->update(['image' => $novoNome]);
                unlink($arquivo);
            }

            $resumo['movidas']++;
        }

        $this->newLine();
        $this->info($simular ? 'SIMULAÇÃO: nada foi movido.' : 'Migração concluída.');

        $this->table(['Resultado', 'Quantidade'], [
            ['Imagens movidas para a pasta privada', $resumo['movidas']],
            ['Já estavam na pasta privada', $resumo['ja_privadas']],
            ['Arquivos não encontrados', $resumo['nao_encontradas']],
            ['Enviadas para a quarentena (não eram imagens)', $resumo['quarentena']],
        ]);

        // Arquivos que ficaram em public/img/tasks sem nenhuma tarefa ligada a eles
        $orfaos = [];

        foreach (glob($origem.DIRECTORY_SEPARATOR.'*') ?: [] as $caminho) {
            if (is_file($caminho) && ! in_array(basename($caminho), $usados, true)) {
                $orfaos[] = basename($caminho);
            }
        }

        if ($orfaos) {
            $this->warn('Arquivos em public/img/tasks que não pertencem a nenhuma tarefa (revise e apague): '.implode(', ', $orfaos));
        }

        return self::SUCCESS;
    }
}
