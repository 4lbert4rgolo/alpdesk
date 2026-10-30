<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Grupo padrão do cadastro
    |--------------------------------------------------------------------------
    |
    | Slug do grupo em que toda pessoa que se cadastra entra automaticamente.
    | Use null para não adicionar ninguém a nenhum grupo.
    |
    */

    'grupo_padrao_cadastro' => 'solicitante',

    /*
    |--------------------------------------------------------------------------
    | Permissões disponíveis
    |--------------------------------------------------------------------------
    |
    | Organizadas por seção: 'chave' => 'Descrição mostrada na tela de grupos'.
    | Para criar uma nova permissão (por exemplo, para o módulo de equipamentos),
    | basta incluir uma linha aqui e protegê-la com @can('chave') ou com a
    | middleware can:chave nas rotas.
    |
    */

    'secoes' => [
        'Tarefas' => [
            'tarefas.criar' => 'Abrir tarefas',
            'tarefas.assumir' => 'Assumir tarefas (virar responsável)',
            'tarefas.gerenciar_todas' => 'Editar e excluir tarefas de qualquer pessoa',
        ],
        'Equipamentos' => [
            'equipamentos.ver' => 'Ver o inventário de equipamentos',
            'equipamentos.gerenciar' => 'Cadastrar e editar equipamentos',
            'equipamentos.importar' => 'Importar equipamentos por planilha',
        ],
        'Painel' => [
            'painel.ver' => 'Ver o painel de indicadores',
        ],
        'Administração' => [
            'usuarios.gerenciar' => 'Criar e editar usuários',
            'grupos.gerenciar' => 'Criar e editar grupos de acesso',
        ],
    ],

];
