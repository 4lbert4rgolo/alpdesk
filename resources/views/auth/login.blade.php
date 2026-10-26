<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <h1 class="text-2xl font-bold text-graphite">Entrar</h1>
        <p class="mt-1 text-sm text-gray-600">Use seu e-mail e sua senha para acessar as tarefas.</p>

        @session('status')
            <div class="mt-4 rounded-lg border border-brand-light/40 bg-brand-soft p-3 text-sm font-medium text-brand-dark" role="status">
                {{ $value }}
            </div>
        @endsession

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <x-label for="email" value="E-mail" />
                <x-input id="email" class="block mt-1 w-full {{ $errors->has('email') ? 'border-red-400' : '' }}" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                <x-input-error for="email" class="mt-2" />
            </div>

            <div>
                <x-label for="password" value="Senha" />
                <x-password-input id="password" name="password" autocomplete="current-password" :invalid="$errors->has('password')" />
                <x-input-error for="password" class="mt-2" />
            </div>

            <div class="flex items-center justify-between">
                <label for="remember_me" class="flex items-center">
                    <x-checkbox id="remember_me" name="remember" />
                    <span class="ms-2 text-sm text-gray-600">Lembrar de mim</span>
                </label>

                @if (Route::has('password.request'))
                    <a class="rounded-md text-sm font-medium text-brand hover:text-brand-dark hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-light" href="{{ route('password.request') }}">
                        Esqueceu a senha?
                    </a>
                @endif
            </div>

            <x-button class="w-full" data-loading-text="Entrando…">
                Entrar
            </x-button>
        </form>

        @if (Route::has('register'))
            <div class="mt-8 border-t border-gray-200 pt-6 text-center">
                <p class="text-sm text-gray-600">Ainda não tem conta?</p>
                <a href="{{ route('register') }}" class="mt-3 inline-flex w-full items-center justify-center rounded-lg border border-brand px-5 py-3 text-sm font-semibold text-brand transition hover:bg-brand-soft focus:outline-none focus:ring-2 focus:ring-brand-light focus:ring-offset-2">
                    Criar conta
                </a>
            </div>
        @endif
    </x-authentication-card>
</x-guest-layout>
