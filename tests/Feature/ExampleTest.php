<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A página inicial exige login: o visitante é enviado para a tela de entrada.
     */
    public function test_visitante_na_pagina_inicial_vai_para_o_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
