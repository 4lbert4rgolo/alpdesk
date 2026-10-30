<?php

namespace Tests\Feature;

use Tests\TestCase;

class LogoTest extends TestCase
{
    public function test_logo_aparece_como_imagem(): void
    {
        $this->blade('<x-logo />')->assertSee('alpdesk_logo.png', false);
    }

    public function test_logo_inexistente_volta_para_o_padrao(): void
    {
        config(['alpdesk.logo' => 'img/arquivo-que-nao-existe.png']);

        $this->blade('<x-logo />')
            ->assertSee('alpdesk_logo.png', false)
            ->assertDontSee('arquivo-que-nao-existe', false);
    }

    public function test_comando_de_conferencia_roda(): void
    {
        $this->artisan('alpdesk:logo')->assertExitCode(0);
    }
}
