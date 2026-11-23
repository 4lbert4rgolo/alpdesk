<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Situação (status) das tarefas
    |--------------------------------------------------------------------------
    |
    | 'chave' => 'Texto mostrado na tela'. As chaves ficam gravadas no banco,
    | então não mude uma chave que já está em uso; mude só o texto.
    |
    */

    'status' => [
        'aberta' => 'Aberta',
        'em_andamento' => 'Em andamento',
        'aguardando' => 'Aguardando',
        'concluida' => 'Concluída',
        'cancelada' => 'Cancelada',
    ],

    // Situações que contam como "em aberto" (listas, painel e prazos vencidos)
    'status_abertos' => ['aberta', 'em_andamento', 'aguardando'],

    /*
    |--------------------------------------------------------------------------
    | Prioridades
    |--------------------------------------------------------------------------
    |
    | Número gravado no banco => [texto, explicação mostrada no formulário].
    | Quanto maior o número, mais alto a tarefa aparece nas listas.
    |
    */

    'prioridades' => [
        1 => ['Baixa', 'Pode esperar, não atrapalha o trabalho.'],
        2 => ['Média', 'Atrapalha, mas dá para trabalhar enquanto aguarda.'],
        3 => ['Alta', 'Atrapalha bastante uma pessoa ou um setor.'],
        4 => ['Urgente', 'Parou o trabalho ou a venda.'],
    ],

    'prioridade_padrao' => 2,

    /*
    |--------------------------------------------------------------------------
    | Avisos por e-mail
    |--------------------------------------------------------------------------
    |
    | true: o sistema avisa por e-mail quando uma tarefa é aberta, assumida,
    | comentada, muda de situação ou quando alguém vira participante.
    | Sem servidor de e-mail configurado, deixe MAIL_MAILER=log no .env
    | (os e-mails vão para storage/logs) ou TASKS_EMAIL_NOTIFICATIONS=false.
    |
    */

    'notificar_por_email' => (bool) env('TASKS_EMAIL_NOTIFICATIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Setor padrão
    |--------------------------------------------------------------------------
    |
    | Nome do setor que já vem escolhido ao abrir uma tarefa, e que recebe as
    | tarefas criadas sem setor. Os setores que recebem tarefas são marcados em
    | Administração > Setores e lojas.
    |
    */

    'setor_padrao' => 'TI',

];
