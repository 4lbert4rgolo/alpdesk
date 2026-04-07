<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title')</title>

        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700;900&display=swap" rel="stylesheet">
        <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2280%22 font-family=%22sans-serif%22 font-weight=%22bold%22 fill=%22%23169681%22>ALP</text></svg>">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        
        <link rel="stylesheet" href="/css/styles.css">
        <script src="/js/scripts.js"></script> 
    </head>
    <body>
        <header>
            <nav class="navbar navbar-expand-lg navbar-light">
                <div class="container-fluid" id="navbar">
                    <a href="/" class="navbar-brand">
                        <img src="/img/alpdesk_logo.png" alt="AlpDesk Solutions">
                    </a>
                    
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-nav">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="collapse navbar-collapse" id="navbar-nav">
                        <ul class="navbar-nav ms-auto">
                           <li class="nav-item">
                                <a href="/" class="nav-link">Tarefas</a>
                           </li>
                           <li class="nav-item">
                                <a href="/tasks/create" class="nav-link">Criar Tarefas</a>
                           </li>
                           @auth
                            <li class="nav-item">
                                <a href="/dashboard" class="nav-link">Minhas Tarefas</a>
                           </li>
                            <li class="nav-item">
                                <form action="/logout" method="POST">
                                    @csrf
                                    <a href="/logout" 
                                       class="nav-link"
                                       onclick="event.preventDefault();
                                       this.closest('form').submit();">
                                       Sair
                                    </a>
                                </form>

                           </li>       
                           @endauth
                           @guest
                             <li class="nav-item">
                                <a href="/login" class="nav-link">Entrar</a>
                           </li>
                            <li class="nav-item">
                                <a href="/register" class="nav-link">Cadastrar</a>
                           </li>
                           @endguest
                        </ul>
                    </div>
                </div>
            </nav>
        </header>

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