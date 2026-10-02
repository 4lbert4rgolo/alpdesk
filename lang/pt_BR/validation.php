<?php

/*
 * Mensagens de validação em português.
 * Regras que não estiverem listadas aqui usam o texto padrão em inglês (fallback).
 */

return [
    'accepted' => 'Você precisa aceitar :attribute para continuar.',
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'confirmed' => 'A confirmação de :attribute não corresponde.',
    'current_password' => 'A senha atual está incorreta.',
    'date' => 'O campo :attribute não é uma data válida.',
    'email' => 'Informe um e-mail válido em :attribute.',
    'exists' => 'O valor selecionado em :attribute é inválido.',
    'image' => 'O arquivo em :attribute deve ser uma imagem.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'max' => [
        'array' => 'O campo :attribute não pode ter mais de :max itens.',
        'file' => 'O arquivo em :attribute não pode ser maior que :max KB.',
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'mimes' => 'O arquivo em :attribute deve ser do tipo: :values.',
    'min' => [
        'array' => 'O campo :attribute deve ter pelo menos :min itens.',
        'file' => 'O arquivo em :attribute deve ter pelo menos :min KB.',
        'numeric' => 'O campo :attribute deve ser pelo menos :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],
    'numeric' => 'O campo :attribute deve ser um número.',
    'password' => [
        'letters' => 'O campo :attribute deve conter pelo menos uma letra.',
        'mixed' => 'O campo :attribute deve conter pelo menos uma letra maiúscula e uma minúscula.',
        'numbers' => 'O campo :attribute deve conter pelo menos um número.',
        'symbols' => 'O campo :attribute deve conter pelo menos um símbolo.',
        'uncompromised' => 'Essa senha apareceu em um vazamento de dados. Escolha outra senha em :attribute.',
    ],
    'required' => 'Preencha o campo :attribute.',
    'same' => 'Os campos :attribute e :other devem ser iguais.',
    'string' => 'O campo :attribute deve ser um texto.',
    'unique' => 'Já existe um cadastro com esse valor em :attribute.',
    'uploaded' => 'Não foi possível enviar o arquivo em :attribute.',
    'url' => 'Informe um endereço válido em :attribute.',

    'custom' => [],

    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'password_confirmation' => 'confirmação da senha',
        'current_password' => 'senha atual',
        'terms' => 'os termos de uso e a política de privacidade',
        'code' => 'código',
        'recovery_code' => 'código de recuperação',
    ],
];
