<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Domínios de e-mail autorizados a criar conta
    |--------------------------------------------------------------------------
    |
    | Informe os domínios separados por vírgula no .env, por exemplo:
    |   REGISTRATION_ALLOWED_DOMAINS=empresa.com.br,filial.com.br
    |
    | Deixe vazio para permitir cadastro com qualquer e-mail.
    |
    */

    'allowed_email_domains' => array_values(array_filter(array_map(
        fn ($dominio) => ltrim(strtolower(trim($dominio)), '@'),
        explode(',', (string) env('REGISTRATION_ALLOWED_DOMAINS', ''))
    ))),

];
