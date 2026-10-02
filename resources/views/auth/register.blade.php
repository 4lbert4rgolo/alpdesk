<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <h1 class="text-2xl font-bold text-graphite">Criar conta</h1>
        <p class="mt-1 text-sm text-gray-600">Preencha os dados abaixo para começar a usar o AlpDesk.</p>

        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <x-label for="name" value="Nome" />
                <x-input id="name" class="block mt-1 w-full {{ $errors->has('name') ? 'border-red-400' : '' }}" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                <x-input-error for="name" class="mt-2" />
            </div>

            <div>
                <x-label for="email" value="E-mail" />
                <x-input id="email" class="block mt-1 w-full {{ $errors->has('email') ? 'border-red-400' : '' }}" type="email" name="email" :value="old('email')" required autocomplete="username" />
                @if(! empty(config('alpdesk.allowed_email_domains')))
                    <p class="mt-1 text-xs text-gray-500">
                        Cadastro permitido apenas para e-mails {{ collect(config('alpdesk.allowed_email_domains'))->map(fn ($dominio) => '@'.$dominio)->join(', ', ' ou ') }}.
                    </p>
                @endif
                <x-input-error for="email" class="mt-2" />
            </div>

            <div>
                <x-label for="password" value="Senha" />
                <x-password-input id="password" name="password" autocomplete="new-password" :invalid="$errors->has('password')" />

                <div data-strength-for="password" class="mt-2" aria-live="polite">
                    <div class="flex gap-1" aria-hidden="true">
                        <span data-bar class="h-1.5 flex-1 rounded-full bg-gray-200 transition-colors"></span>
                        <span data-bar class="h-1.5 flex-1 rounded-full bg-gray-200 transition-colors"></span>
                        <span data-bar class="h-1.5 flex-1 rounded-full bg-gray-200 transition-colors"></span>
                        <span data-bar class="h-1.5 flex-1 rounded-full bg-gray-200 transition-colors"></span>
                    </div>
                    <p data-strength-text class="mt-1 min-h-4 text-xs font-medium text-gray-700"></p>
                </div>

                <p class="mt-1 text-xs text-gray-500">Mínimo de 8 caracteres. Misture letras, números e símbolos para uma senha mais forte.</p>
                <x-input-error for="password" class="mt-2" />
            </div>

            <div>
                <x-label for="password_confirmation" value="Confirmar senha" />
                <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" :invalid="$errors->has('password_confirmation')" />
                <x-input-error for="password_confirmation" class="mt-2" />
            </div>

            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                <div>
                    <x-label for="terms">
                        <div class="flex items-center">
                            <x-checkbox name="terms" id="terms" required />

                            <div class="ms-2">
                                {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                        'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="rounded-md text-sm font-medium text-brand hover:text-brand-dark hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-light">'.__('Terms of Service').'</a>',
                                        'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="rounded-md text-sm font-medium text-brand hover:text-brand-dark hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-light">'.__('Privacy Policy').'</a>',
                                ]) !!}
                            </div>
                        </div>
                    </x-label>
                    <x-input-error for="terms" class="mt-2" />
                </div>
            @endif

            <x-button class="w-full" data-loading-text="Criando conta…">
                Criar conta
            </x-button>
        </form>

        <div class="mt-8 border-t border-gray-200 pt-6 text-center">
            <p class="text-sm text-gray-600">Já tem conta?</p>
            <a href="{{ route('login') }}" class="mt-3 inline-flex w-full items-center justify-center rounded-lg border border-brand px-5 py-3 text-sm font-semibold text-brand transition hover:bg-brand-soft focus:outline-none focus:ring-2 focus:ring-brand-light focus:ring-offset-2">
                Entrar
            </a>
        </div>
    </x-authentication-card>
</x-guest-layout>
