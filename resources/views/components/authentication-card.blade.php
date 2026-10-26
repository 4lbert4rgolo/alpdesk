<div class="min-h-screen lg:grid lg:grid-cols-2 bg-white">
    {{-- Painel da marca (somente telas grandes) --}}
    <aside class="hidden lg:flex flex-col justify-between p-12 text-white bg-gradient-to-br from-graphite via-graphite to-brand-dark">
        <div class="inline-flex self-start rounded-2xl bg-white px-6 py-4 shadow-lg">
            {{ $logo }}
        </div>

        <div class="max-w-md">
            <h2 class="text-4xl font-bold leading-tight">
                Organize as tarefas e o suporte da sua equipe.
            </h2>
            <p class="mt-4 text-lg text-white/80">
                Abra, acompanhe e finalize tarefas em um só lugar.
            </p>
        </div>

        <p class="text-sm text-white/60">&copy; {{ date('Y') }} AlpDesk Solutions</p>
    </aside>

    {{-- Formulário --}}
    <main class="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-6 py-10 lg:bg-white">
        <div class="mb-8 lg:hidden">
            {{ $logo }}
        </div>

        <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-md lg:p-0 lg:shadow-none">
            {{ $slot }}
        </div>
    </main>
</div>

@once
    <script>
        (function () {
            // Mostrar / ocultar senha
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-toggle-password]');
                if (!btn) return;
                var input = document.getElementById(btn.getAttribute('data-toggle-password'));
                if (!input) return;
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
                btn.querySelector('[data-eye-open]').classList.toggle('hidden', show);
                btn.querySelector('[data-eye-closed]').classList.toggle('hidden', !show);
            });

            // Indicador de força da senha
            var labels = ['Muito fraca', 'Fraca', 'Razoável', 'Boa', 'Forte'];
            var colors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-lime-600', 'bg-brand-light'];
            document.querySelectorAll('[data-strength-for]').forEach(function (meter) {
                var input = document.getElementById(meter.getAttribute('data-strength-for'));
                var bars = meter.querySelectorAll('[data-bar]');
                var text = meter.querySelector('[data-strength-text]');
                if (!input) return;
                input.addEventListener('input', function () {
                    var v = input.value, score = 0;
                    if (v.length >= 8) score++;
                    if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
                    if (/\d/.test(v)) score++;
                    if (/[^A-Za-z0-9]/.test(v)) score++;
                    if (!v) { score = -1; }
                    bars.forEach(function (bar, i) {
                        colors.forEach(function (c) { bar.classList.remove(c); });
                        bar.classList.toggle('bg-gray-200', i > score || score < 0);
                        if (i <= score && score >= 0) bar.classList.add(colors[score]);
                    });
                    text.textContent = score < 0 ? '' : 'Senha: ' + labels[score].toLowerCase();
                });
            });

            // Botão com "carregando" ao enviar (evita clique duplo)
            document.addEventListener('submit', function (e) {
                var form = e.target;
                var btn = form.querySelector('[data-loading-text]');
                if (!btn || btn.disabled) return;
                btn.dataset.originalText = btn.textContent.trim();
                btn.textContent = btn.getAttribute('data-loading-text');
                btn.disabled = true;
            });
            window.addEventListener('pageshow', function () {
                document.querySelectorAll('[data-loading-text]').forEach(function (btn) {
                    if (btn.dataset.originalText) {
                        btn.textContent = btn.dataset.originalText;
                        btn.disabled = false;
                    }
                });
            });
        })();
    </script>
@endonce
