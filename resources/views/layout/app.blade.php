<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/style.css?v=') . time() }}">
    <title>Gamespy</title>
</head>

<body>
    <header class="sticky">
        <nav>
            <ul class="header">
                    <li class="home"><a href="{{ route('home') }}">Gamespy
                            <x-spy-icon class="spy-icon" />
                        </a>
                    </li>
                    <li class="search-item">
                        <form action="{{ route('search') }}" method="GET" autocomplete="off">
                            <input class="search-input" type="search" name="q" placeholder="Search game">
                            <button class="search-btn" type="submit"><x-search-icon /></button>
                        </form>
                    </li>
                    <li class="dropdown">
                        <button class="dropdown-btn">Genres<span class="arrow"><x-arrow-down/></span></button>
                        <ul class="dropdown-genres">
                            <li><a href="">Action</a></li>
                            <li><a href="">Adventure</a></li>
                            <li><a href="">RPG</a></li>
                            <li><a href="">Strategy</a></li>
                            <li><a href="">Sports</a></li>
                            <li><a href="">Simulation</a></li>
                            <li><a href="">Racing</a></li>
                            <li><a href="">Indie</a></li>
                            <li><a href="">Casual</a></li>
                            <li><a href="">Massively Multiplayer</a></li>
                            <li><a href="">Free to Play</a></li>
                        </ul>
                    </li>
                    <li><a href="{{ route('wishlist') }}">Wishlist</a></li>
                @guest
                    <li><a class="logout-btn" href="{{ route('login') }}">Sign in</a></li>
                @endguest
                @auth
                    <li><form action="{{ route('logout') }}" method="POST" style="display: inline;">
                        @csrf
                        <button class="logout-btn" type="submit">Logout</button>
                    </form></li>
                @endauth
            </ul>
        </nav>
    </header>
    
    <div class="content-wrapper">
        @yield('content')
    </div>

    <footer>
        <p>&copy; 2026 Gamespy. All rights reserved.</p>
    </footer>

    <script src="{{ asset('js/app.js?v=') . time() }}"></script>
</body>

</html>
