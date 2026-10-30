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

    /*
    |--------------------------------------------------------------------------
    | Visibilidade das tarefas
    |--------------------------------------------------------------------------
    |
    | true: quem não pode assumir tarefas (por exemplo, o grupo Solicitante) vê
    | somente as tarefas que criou ou em que é responsável. Quem tem as permissões
    | "tarefas.assumir" ou "tarefas.gerenciar_todas" continua vendo todas.
    | false: todos os usuários logados veem todas as tarefas.
    |
    */

    'visibilidade_restrita' => (bool) env('TASKS_VISIBILITY_RESTRICTED', true),

    /*
    |--------------------------------------------------------------------------
    | Forçar HTTPS
    |--------------------------------------------------------------------------
    |
    | Em produção atrás de um proxy HTTPS, use FORCE_HTTPS=true no .env.
    |
    */

    'force_https' => (bool) env('FORCE_HTTPS', false),

    /*
    |--------------------------------------------------------------------------
    | Logo
    |--------------------------------------------------------------------------
    |
    | Caminho do logo dentro da pasta public. Para trocar, substitua o arquivo
    | (ou aponte APP_LOGO no .env para outro). A tela adiciona a data do arquivo
    | ao endereço, então o navegador mostra o novo logo sem precisar limpar o cache.
    |
    */

    'logo' => env('APP_LOGO', 'img/alpdesk_logo.png'),

];
