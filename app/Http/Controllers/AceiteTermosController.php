<?php

namespace App\Http\Controllers;

use App\Models\TermAcceptance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Tela de aceite dos Termos de Uso e da Política de Privacidade (LGPD).
 * Quem chega aqui é mandado pelo middleware ExigirAceiteTermos.
 */
class AceiteTermosController extends Controller
{
    public function show(Request $request)
    {
        // Quem já aceitou a versão atual não precisa ver a tela de novo
        if ($request->user()->aceitouTermosAtuais()) {
            return redirect()->intended('/');
        }

        return view('termos.aceite', [
            'versao' => config('alpdesk.termos_versao'),
            // Já aceitou alguma versão antes? Então o texto da tela fala em "nova versão"
            'jaAceitouAntes' => $request->user()->aceitesTermos()->exists(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(
            ['aceite' => ['accepted']],
            ['aceite.accepted' => 'Para continuar, marque que leu e concorda com os Termos de Uso e a Política de Privacidade.']
        );

        TermAcceptance::registrar($request->user(), $request);

        $request->session()->put('termos_aceitos.'.$request->user()->id, (string) config('alpdesk.termos_versao'));

        return redirect()->intended('/')->with('msg', 'Obrigado! Seu aceite foi registrado.');
    }

    /**
     * "Não concordo": a pessoa sai do sistema. Sem o aceite, o acesso não é liberado.
     */
    public function recusar(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with(
            'status',
            'Você saiu do sistema. Para usar o AlpDesk é preciso aceitar os Termos de Uso e a Política de Privacidade. Em caso de dúvida, fale com o TI.'
        );
    }
}
