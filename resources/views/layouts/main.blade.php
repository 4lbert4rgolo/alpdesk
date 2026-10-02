<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title')</title>

        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700;900&display=swap" rel="stylesheet">
        <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2280%22 font-family=%22sans-serif%22 font-weight=%22bold%22 fill=%22%23169681%22>ALP</text></svg>">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        
        <link rel="stylesheet" href="{{ asset('CSS/styles.css') }}?v={{ @filemtime(public_path('CSS/styles.css')) }}">
    </head>
    <body>
        <header>
            <div class="topbar">
                <button class="menu-toggle" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#sidebar"
                        aria-controls="sidebar" aria-label="Abrir menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <a href="/" class="navbar-brand">
                    <img src="/img/alpdesk_logo.png" alt="AlpDesk Solutions">
                </a>
            </div>
        </header>

        <aside class="offcanvas offcanvas-start sidebar" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
            <div class="sidebar-header">
                <a href="/" class="navbar-brand" id="sidebarLabel">
                    <img src="/img/alpdesk_logo.png" alt="AlpDesk Solutions">
                </a>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar menu"></button>
            </div>

            @auth
                <div class="sidebar-user">
                    <ion-icon name="person-circle-outline"></ion-icon>
                    <span>{{ Auth::user()->name }}</span>
                </div>
            @endauth

            <nav class="sidebar-nav">
                <a href="/" class="sidebar-link {{ request()->is('/') ? 'active' : '' }}">
                    <ion-icon name="home-outline"></ion-icon> Tarefas
                </a>
                <a href="/tasks/create" class="sidebar-link {{ request()->is('tasks/create') ? 'active' : '' }}">
                    <ion-icon name="add-circle-outline"></ion-icon> Criar Tarefas
                </a>
                @auth
                    <a href="/dashboard" class="sidebar-link {{ request()->is('dashboard') ? 'active' : '' }}">
                        <ion-icon name="list-outline"></ion-icon> Minhas Tarefas
                    </a>
                @endauth
            </nav>

            <div class="sidebar-footer">
                @auth
                    <form action="/logout" method="POST">
                        @csrf
                        <button type="submit" class="sidebar-link">
                            <ion-icon name="log-out-outline"></ion-icon> Sair
                        </button>
                    </form>
                @endauth
                @guest
                    <a href="/login" class="sidebar-link {{ request()->is('login') ? 'active' : '' }}">
                        <ion-icon name="log-in-outline"></ion-icon> Entrar
                    </a>
                    <a href="/register" class="sidebar-link {{ request()->is('register') ? 'active' : '' }}">
                        <ion-icon name="person-add-outline"></ion-icon> Cadastrar
                    </a>
                @endguest
            </div>
        </aside>

        <main>
            <div class="container-fluid p-0">
                @if(session('msg'))
                    <p class="msg">{{ session('msg') }}</p>
                @endif
                
                @yield('content')
            </div>
        </main>

        <footer>
            <p><strong>AlpDesk Solutions</strong> &copy; 2026</p>
            <p style="font-size: 12px; margin-top: 5px; opacity: 0.7;">
                Desenvolvido por Albert Argolo
            </p>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
        <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    </body>
</html>