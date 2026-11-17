<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tipos de equipamento
    |--------------------------------------------------------------------------
    |
    | Lista usada nos filtros e no formulário. Para criar um tipo novo, basta
    | incluir uma linha aqui.
    |
    */

    'tipos' => [
        'Desktop',
        'Notebook',
        'Tablet',
        'Celular',
        'Monitor',
        'Impressora',
        'Mouse',
        'Teclado',
        'Energia',
        'Telefone',
        'TV',
        'Áudio',
        'Câmeras e DVR',
        'Mobiliário',
        'Climatização',
        'Outros',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tipos da área de informática
    |--------------------------------------------------------------------------
    |
    | As tarefas só podem ser ligadas a equipamentos destes tipos. Tipos que não
    | estão aqui (Mobiliário, Climatização, Outros) ficam fora das tarefas.
    |
    */

    'tipos_informatica' => [
        'Desktop',
        'Notebook',
        'Tablet',
        'Celular',
        'Monitor',
        'Impressora',
        'Mouse',
        'Teclado',
        'Energia',
        'Telefone',
        'TV',
        'Áudio',
        'Câmeras e DVR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Situação do equipamento
    |--------------------------------------------------------------------------
    |
    | 'chave' => 'Texto mostrado na tela'.
    |
    */

    'status' => [
        'em_uso' => 'Em uso',
        'estoque' => 'Em estoque',
        'manutencao' => 'Em manutenção',
        'baixado' => 'Baixado',
    ],

];
