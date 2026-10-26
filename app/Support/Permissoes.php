<?php

namespace App\Support;

class Permissoes
{
    /**
     * Permissões agrupadas por seção, como definidas em config/permissoes.php.
     *
     * @return array<string, array<string, string>>
     */
    public static function secoes(): array
    {
        return config('permissoes.secoes', []);
    }

    /**
     * Todas as permissões em uma lista só: chave => descrição.
     *
     * @return array<string, string>
     */
    public static function todas(): array
    {
        $todas = [];

        foreach (self::secoes() as $itens) {
            $todas += $itens;
        }

        return $todas;
    }

    /**
     * @return array<int, string>
     */
    public static function chaves(): array
    {
        return array_keys(self::todas());
    }
}
