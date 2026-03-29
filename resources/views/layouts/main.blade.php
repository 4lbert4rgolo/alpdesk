<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title')</title>

        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700;900&display=swap" rel="stylesheet">
        
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        
        <link rel="stylesheet" href="/css/styles.css">
        <script src="/js/scripts.js"></script> 
    </head>
    <body>
        <header>
            <nav class="navbar navbar-expand-lg navbar-light">
                <div class="container-fluid" id="navbar">
                    <a href="/" class="navbar-brand">
                        <img src="/img/eixdesk_logo.png" alt="AlpDesk Solutions">
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
                           <li class="nav-item">
                                <a href="/" class="nav-link">Entrar</a>
                           </li>
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