<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="{{ asset('css/style.css?v=') . time() }}">
    <title>Home</title>
</head>

<body>
    <header class="sticky">
        <nav>
            <ul class="header">
                <li class="home"><a href="{{ route('home') }}">Gamespy
                    <x-spy-icon class="spy-icon"/>
                </a>
                </li>
                <li class="search-item">
                    <form action="{{ route('search') }}" method="GET">
                        <input class="search-input" type="search" name="q" placeholder="Search game">
                        <button class="search-btn" type="submit"><x-search-icon/></button>
                    </form>
                </li>
                <li><a href="{{ route('trending') }}">Trending</a></li>
                <li><a href="{{ route('genres')}}">Genres</a></li>
                <li><a href="">Wishlist</a></li>
                <li class="login"><a href="{{ route('login') }}"><x-user-icon/></a></li>
            </ul>
        </nav>
    </header>

    @yield('content')

    <footer>
        <p>&copy; 2026 Gamespy. All rights reserved.</p>
    </footer>
</body>

</html>
