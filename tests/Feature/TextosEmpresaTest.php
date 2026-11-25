<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Os dados da empresa (razão social, CNPJ, Encarregado...) vêm do .env, não do código.
 */
class TextosEmpresaTest extends TestCase
{
    public function test_termos_e_politica_usam_os_dados_do_env(): void
    {
        config([
            'empresa.nome' => 'Empresa Exemplo Ltda',
            'empresa.cnpj' => '11.222.333/0001-44',
            'empresa.endereco' => 'Rua Um, 100, Centro',
            'empresa.encarregado_nome' => 'Maria Souza',
            'empresa.encarregado_email' => 'privacidade@exemplo.com.br',
            'empresa.prazo_guarda_anos' => 3,
        ]);

        $this->get(route('terms.show'))
            ->assertOk()
            ->assertSee('Empresa Exemplo Ltda')
            ->assertSee('11.222.333/0001-44')
            ->assertSee('privacidade@exemplo.com.br')
            ->assertDontSee('{empresa_nome}');

        $this->get(route('policy.show'))
            ->assertOk()
            ->assertSee('Rua Um, 100, Centro')
            ->assertSee('Maria Souza')
            ->assertSeeText('3 anos');
    }

    public function test_campo_vazio_aparece_como_aviso_para_preencher(): void
    {
        config(['empresa.nome' => null]);

        $this->get(route('terms.show'))->assertSee('[preencha a razão social no cadastro da empresa]');
    }

    public function test_html_no_env_nao_vira_codigo(): void
    {
        config(['empresa.nome' => '<script>alert(1)</script>']);

        $this->get(route('terms.show'))->assertDontSee('<script>alert(1)</script>', false);
    }
}
