<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <h1 class="text-2xl font-bold text-graphite">
            {{ $jaAceitouAntes ? 'Os termos foram atualizados' : 'Antes de começar' }}
        </h1>
        <p class="mt-2 text-sm text-gray-600">
            @if ($jaAceitouAntes)
                Publicamos uma nova versão dos Termos de Uso e da Política de Privacidade. Leia e confirme para continuar usando o AlpDesk.
            @else
                Para usar o AlpDesk, leia e aceite os Termos de Uso e a Política de Privacidade, de acordo com a Lei Geral de Proteção de Dados (LGPD).
            @endif
        </p>

        {{-- Resumo em linguagem simples. O texto completo está nos links abaixo. --}}
        <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
            <p class="font-medium text-graphite">Em resumo:</p>
            <ul class="mt-2 list-disc space-y-1 ps-5">
                <li>O AlpDesk é de uso exclusivo para o trabalho, e seu acesso é pessoal.</li>
                <li>Usamos seu nome, e-mail, setor e loja para liberar o acesso e organizar os chamados.</li>
                <li>Chamados, comentários, chat e equipamentos ficam registrados com o seu nome.</li>
                <li>Registramos acessos (data, hora e IP) para a segurança do sistema.</li>
                <li>Não registre dados sensíveis nem documentos pessoais sem necessidade.</li>
                <li>Você pode pedir acesso, correção ou informações sobre seus dados ao Encarregado.</li>
            </ul>
        </div>

        <div class="mt-4 flex flex-col gap-2 text-sm sm:flex-row sm:gap-6">
            <a href="{{ route('terms.show') }}" target="_blank" rel="noopener" class="rounded-md font-medium text-brand hover:text-brand-dark hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-light">
                Ler os Termos de Uso
            </a>
            <a href="{{ route('policy.show') }}" target="_blank" rel="noopener" class="rounded-md font-medium text-brand hover:text-brand-dark hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-light">
                Ler a Política de Privacidade
            </a>
        </div>

        <form method="POST" action="{{ route('termos.aceitar') }}" class="mt-6">
            @csrf

            <label for="aceite" class="flex items-start gap-3 text-sm text-gray-700">
                <x-checkbox name="aceite" id="aceite" value="1" required class="mt-0.5" />
                <span>
                    Li e concordo com os <strong>Termos de Uso</strong> e declaro estar ciente da
                    <strong>Política de Privacidade</strong> do AlpDesk.
                </span>
            </label>
            <x-input-error for="aceite" class="mt-2" />

            <x-button class="mt-6 w-full" data-loading-text="Registrando…">
                Aceitar e continuar
            </x-button>

            <p class="mt-3 text-xs text-gray-500">
                Versão {{ rescue(fn () => \Illuminate\Support\Carbon::parse($versao)->format('d/m/Y'), $versao, false) }}. Seu aceite fica registrado com data, hora e endereço IP.
            </p>
        </form>

        <div class="mt-6 flex items-center justify-between border-t border-gray-200 pt-4 text-sm">
            <span class="text-gray-500">Não concorda?</span>

            <form method="POST" action="{{ route('termos.recusar') }}">
                @csrf

                <button type="submit" class="rounded-md font-medium text-gray-600 hover:text-gray-900 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-light">
                    Recusar e sair
                </button>
            </form>
        </div>
    </x-authentication-card>
</x-guest-layout>
