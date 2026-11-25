<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Confere se o CNPJ é válido pelos dígitos verificadores (os dois últimos números).
 *
 * Como funciona: cada um dos 12 primeiros dígitos é multiplicado por um peso
 * (5,4,3,2,9,8,7,6,5,4,3,2); a soma, dividida por 11, dá o 1º dígito verificador.
 * Repete com 13 dígitos e outros pesos para achar o 2º. Se não bater, o CNPJ foi
 * digitado errado.
 */
class Cnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::valido((string) $value)) {
            $fail('CNPJ inválido. Confira os números.');
        }
    }

    public static function somenteNumeros(string $cnpj): string
    {
        return preg_replace('/\D/', '', $cnpj) ?? '';
    }

    /** 11222333000181 -> 11.222.333/0001-81 */
    public static function formatar(string $cnpj): string
    {
        $n = self::somenteNumeros($cnpj);

        return strlen($n) === 14
            ? substr($n, 0, 2).'.'.substr($n, 2, 3).'.'.substr($n, 5, 3).'/'.substr($n, 8, 4).'-'.substr($n, 12, 2)
            : $cnpj;
    }

    public static function valido(string $cnpj): bool
    {
        $n = self::somenteNumeros($cnpj);

        // 14 dígitos e não pode ser tudo igual (00000000000000, 11111111111111...)
        if (strlen($n) !== 14 || preg_match('/^(\d)\1{13}$/', $n)) {
            return false;
        }

        foreach ([12, 13] as $tamanho) {
            $pesos = $tamanho === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $soma = 0;

            for ($i = 0; $i < $tamanho; $i++) {
                $soma += (int) $n[$i] * $pesos[$i];
            }

            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;

            if ((int) $n[$tamanho] !== $digito) {
                return false;
            }
        }

        return true;
    }
}
