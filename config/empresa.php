<?php

/*
|--------------------------------------------------------------------------
| Dados da empresa que usa o AlpDesk
|--------------------------------------------------------------------------
|
| Tudo o que muda de uma empresa para outra fica no .env, nunca no código.
| Assim o mesmo código serve para qualquer empresa, e o repositório no GitHub
| não carrega dados de ninguém. Os textos dos Termos de Uso e da Política de
| Privacidade (resources/markdown) usam estes valores no lugar dos marcadores
| {empresa_nome}, {empresa_cnpj} etc.
|
*/

return [
    'nome' => env('EMPRESA_NOME'),                       // razão social
    'cnpj' => env('EMPRESA_CNPJ'),
    'endereco' => env('EMPRESA_ENDERECO'),

    // Encarregado pelo Tratamento de Dados Pessoais (DPO), exigido pela LGPD
    'encarregado_nome' => env('EMPRESA_ENCARREGADO_NOME'),
    'encarregado_email' => env('EMPRESA_ENCARREGADO_EMAIL'),

    // Prazos citados na Política de Privacidade
    'prazo_guarda_anos' => (int) env('EMPRESA_PRAZO_GUARDA_ANOS', 5),
    'backup_dias' => (int) env('EMPRESA_BACKUP_DIAS', 30),
];
