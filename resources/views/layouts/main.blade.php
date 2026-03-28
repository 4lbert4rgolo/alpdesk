<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

      <title>@yield('title')</title>

      <!-- Fonte do Google  -->
      <link href="https://fonts.googleapis.com/css2?family=Roboto" rel="stylesheet">
      
      <!-- CSS Bootstrap -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
      
      <!-- CSS da aplicação -->
      <link rel="stylesheet" href= "/css/styles.css">
      <script src="/js/scripts.js"></script> 
      
    </head>
    <body>
        <header>
            <nav class="navbar navbar-expand-lg navbar-light">
                <div class="collapse navbar-collapse" id="navbar">
                    <a href="/" class="navbar-brand">
                        <img src="/img/eixdesk_logo.png" alt="Eix Desk">
                    </a>
                    <ul class="navbar-nav">
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
            </nav>
        </header>
        <main>
            <div class="container-fluid">
                <div class="row">
                    @if(session('msg'))
                       <p class="msg">{{ session('msg') }}</p>
                    @endif
                    @yield('content')
                </div>
            </div>

        </main>
        <footer>
            <p>Eix Desk &copy; 2026</p>
        </footer>
        <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
        <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    </body>
</html>
