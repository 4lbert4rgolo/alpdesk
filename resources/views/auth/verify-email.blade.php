<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <h1 class="text-2xl font-bold text-graphite">Confirme seu e-mail</h1>
        <p class="mt-2 text-sm text-gray-600">
            Enviamos um link de confirmação para o seu e-mail. Clique nele para liberar o acesso ao sistema.
            Não recebeu? Podemos enviar outro.
        </p>

        @if (session('status') == 'verification-link-sent')
            <div class="mt-4 rounded-lg border border-brand-light/40 bg-brand-soft p-3 text-sm font-medium text-brand-dark" role="status">
                Enviamos um novo link de verificação para o seu e-mail.
            </div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
            @csrf

            <x-button class="w-full" data-loading-text="Enviando…">
                Reenviar e-mail de verificação
            </x-button>
        </form>

        <div class="mt-6 flex items-center justify-between border-t border-gray-200 pt-4 text-sm">
            <a href="{{ route('profile.show') }}" class="rounded-md font-medium text-brand hover:text-brand-dark hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-light">
                Editar perfil
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button type="submit" class="rounded-md font-medium text-gray-600 hover:text-gray-900 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-light">
                    Sair
                </button>
            </form>
        </div>
    </x-authentication-card>
</x-guest-layout>
