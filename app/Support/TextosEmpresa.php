<?php

namespace App\Support;

use App\Models\Store;
use Illuminate\Support\Str;

/**
 * Monta os Termos de Uso e a Política de Privacidade com os dados da EMPRESA da pessoa
 * (cadastro em Administração > Setores e empresas). Os arquivos em resources/markdown têm
 * marcadores como {empresa_nome}; aqui eles são trocados pelos valores reais.
 *
 * De onde vem cada dado, em ordem:
 *   1. a empresa da pessoa logada (razão social, CNPJ, endereço, Encarregado).
 *      Se a pessoa estiver num LOCAL (ex.: Escritório), vale a empresa responsável por ele;
 *   2. o padrão do .env (config/empresa.php), para quem não tem empresa ou campo vazio;
 *   3. se nenhum dos dois tiver, um aviso "[preencha ...]".
 */
class TextosEmpresa
{
    /** Texto pronto em HTML. $arquivo: "terms" ou "policy" */
    public static function html(string $arquivo, ?Store $empresa = null): string
    {
        // Local (ex.: Escritório) não é empresa: o termo usa a empresa responsável por ele
        $empresa = $empresa?->empresaParaTermos();

        $texto = file_get_contents(resource_path("markdown/{$arquivo}.md"));

        // strtr() troca vários marcadores de uma vez, cada um pelo seu valor
        $texto = strtr($texto, self::substituicoes($empresa));

        // html_input "escape": qualquer HTML digitado no cadastro aparece como texto, sem virar código
        return Str::markdown($texto, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    /** marcador => valor */
    public static function substituicoes(?Store $empresa = null): array
    {
        // $campoEmpresa: coluna na tabela de empresas; $chaveEnv: chave em config/empresa.php
        $valor = function (string $campoEmpresa, string $chaveEnv, string $aviso) use ($empresa) {
            if ($empresa && filled($empresa->{$campoEmpresa})) {
                return (string) $empresa->{$campoEmpresa};
            }

            return filled(config("empresa.{$chaveEnv}")) ? (string) config("empresa.{$chaveEnv}") : "[{$aviso}]";
        };

        return [
            '{empresa_nome}' => $valor('razao_social', 'nome', 'preencha a razão social no cadastro da empresa'),
            '{empresa_fantasia}' => $empresa?->name ?: $valor('razao_social', 'nome', 'preencha o nome da empresa'),
            '{empresa_cnpj}' => $valor('cnpj', 'cnpj', 'preencha o CNPJ no cadastro da empresa'),
            '{empresa_endereco}' => $valor('endereco', 'endereco', 'preencha o endereço no cadastro da empresa'),
            '{encarregado_nome}' => $valor('encarregado_nome', 'encarregado_nome', 'preencha o Encarregado no cadastro da empresa'),
            '{encarregado_email}' => $valor('encarregado_email', 'encarregado_email', 'preencha o e-mail do Encarregado no cadastro da empresa'),
            '{prazo_guarda_anos}' => (string) config('empresa.prazo_guarda_anos', 5),
            '{backup_dias}' => (string) config('empresa.backup_dias', 30),
        ];
    }
}
