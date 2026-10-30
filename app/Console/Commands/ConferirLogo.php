<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ConferirLogo extends Command
{
    protected $signature = 'alpdesk:logo';

    protected $description = 'Confere se o logo e os ícones do site estão no lugar e são imagens válidas';

    public function handle(): int
    {
        $arquivos = [
            'Logo (menu e login)' => config('alpdesk.logo', 'img/alpdesk_logo.png'),
            'Favicon' => 'favicon.ico',
            'Favicon 32 px' => 'img/favicon-32.png',
            'Favicon 16 px' => 'img/favicon-16.png',
            'Ícone do iPhone' => 'img/apple-touch-icon.png',
        ];

        $linhas = [];
        $problemas = 0;

        foreach ($arquivos as $nome => $caminho) {
            $completo = public_path($caminho);

            if (! is_file($completo)) {
                $linhas[] = [$nome, $caminho, 'NÃO ENCONTRADO', '-'];
                $problemas++;

                continue;
            }

            $tamanho = filesize($completo);
            $kb = round($tamanho / 1024).' KB';

            if ($tamanho === 0) {
                $linhas[] = [$nome, $caminho, 'ARQUIVO VAZIO', $kb];
                $problemas++;

                continue;
            }

            $extensao = strtolower(pathinfo($completo, PATHINFO_EXTENSION));

            if ($extensao === 'svg') {
                $inicio = (string) file_get_contents($completo, false, null, 0, 2048);
                $valido = str_contains($inicio, '<svg');
                $linhas[] = [$nome, $caminho, $valido ? 'OK' : 'NÃO É UM SVG VÁLIDO', 'SVG · '.$kb];
                $problemas += $valido ? 0 : 1;

                continue;
            }

            $info = @getimagesize($completo);

            if ($info === false) {
                $linhas[] = [$nome, $caminho, 'NÃO É UMA IMAGEM VÁLIDA', $kb];
                $problemas++;

                continue;
            }

            $linhas[] = [$nome, $caminho, 'OK', "{$info[0]}x{$info[1]} · {$kb} · {$info['mime']}"];
        }

        $this->table(['Item', 'Caminho (dentro de public)', 'Situação', 'Detalhes'], $linhas);

        if ($problemas > 0) {
            $this->newLine();
            $this->warn('Dicas para resolver:');
            $this->line(' - NÃO ENCONTRADO: confira a pasta e o nome. No Windows, ative "Extensões de nomes de arquivos" (aba Exibir)');
            $this->line('   para ver se o arquivo ficou como logo.png.png.');
            $this->line(' - NÃO É UMA IMAGEM VÁLIDA / VAZIO: o arquivo foi renomeado de outro formato (HEIC, WEBP, SVG) ou não foi copiado inteiro. Salve de novo como PNG.');
            $this->line(' - Se você usa APP_LOGO no .env, o caminho é relativo à pasta public (exemplo: img/logo.png).');

            return self::FAILURE;
        }

        $this->info('Tudo certo com o logo e os ícones.');

        return self::SUCCESS;
    }
}
