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
    | Verificação em duas etapas obrigatória para administradores
    |--------------------------------------------------------------------------
    |
    | true: administrador sem a verificação em duas etapas ativada só consegue
    | abrir o perfil (para ativá-la). Recomendado em produção: ADMIN_2FA_REQUIRED=true
    |
    */

    'exigir_2fa_admin' => (bool) env('ADMIN_2FA_REQUIRED', false),

    /*
    |--------------------------------------------------------------------------
    | Aceite dos Termos de Uso e da Política de Privacidade (LGPD)
    |--------------------------------------------------------------------------
    |
    | termos_versao: versão atual dos textos em resources/markdown (terms.md e
    | policy.md). Ao mudar um texto de forma relevante, troque a versão (ex.: a data
    | da mudança): todos terão que aceitar de novo no próximo acesso.
    |
    | exigir_aceite_termos: true = quem ainda não aceitou a versão atual só consegue
    | ver a tela de aceite, os textos e sair. Desligado só nos testes automáticos.
    |
    */

    'termos_versao' => env('TERMS_VERSION', '2026-11-23'),

    'exigir_aceite_termos' => (bool) env('TERMS_ACCEPTANCE_REQUIRED', true),

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

    'logo' => env('APP_LOGO', 'img/icon-192.png'),

];
