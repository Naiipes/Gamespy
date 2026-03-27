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
    <link rel="stylesheet" href="{{ asset('css/style.css?v=') }}">
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
                            <li><a href="{{ route('genres.show', ['genre' => 'action']) }}">Action</a></li>
                            <li><a href="{{ route('genres.show', ['genre' => 'adventure']) }}">Adventure</a></li>
                            <li><a href="{{ route('genres.show', ['genre' => 'rpg']) }}">RPG</a></li>
                            <li><a href="{{ route('genres.show', ['genre' => 'strategy']) }}">Strategy</a></li>
                            <li><a href="{{ route('genres.show', ['genre' => 'sports']) }}">Sports</a></li>
                            <li><a href="{{ route('genres.show', ['genre' => 'simulation']) }}">Simulation</a></li>
                            <li><a href="{{ route('genres.show', ['genre' => 'racing']) }}">Racing</a></li>
                            <li><a href="{{ route('genres.show', ['genre' => 'indie']) }}">Indie</a></li>
                            <li><a href="{{ route('genres.show', ['genre' => 'casual']) }}">Casual</a></li>
                            <li><a href="{{ route('genres.show', ['genre' => 'massively multiplayer']) }}">Massively Multiplayer</a></li>
                        </ul>
                    </li>
                    <li><a href="{{ route('wishlist') }}">Wishlist</a></li>
                    
                    @php
                        $navbarNotification = [
                            'type' => 'Wishlist Sale',
                            'title' => 'Ready or Not',
                            'message' => 'Dropped to $19.99 and matched your target price.',
                            'thumb' => 'https://cdn.akamai.steamstatic.com/steam/apps/1144200/header.jpg',
                            'additional_count' => 1,
                        ];
                    @endphp
                    <li class="navbar-notification" id="navbar-notification">
                        <button class="notification-btn"
                            id="navbar-notification-toggle"
                            type="button"
                            aria-expanded="false"
                            aria-controls="navbar-notification-dropdown">
                            <x-notification-icon />
                        </button>

                        <div class="navbar-notification-dropdown"
                            id="navbar-notification-dropdown"
                            hidden>
                            <div class="navbar-notification-card">
                                <img class="navbar-notification-image"
                                    src="{{ $navbarNotification['thumb'] }}"
                                    alt="{{ $navbarNotification['title'] }}">

                                <div class="navbar-notification-body">
                                    <div class="navbar-notification-meta">
                                        <span class="navbar-notification-type">{{ $navbarNotification['type'] }}</span>
                                    </div>

                                    <p class="navbar-notification-title">{{ $navbarNotification['title'] }}</p>
                                    <p class="navbar-notification-text">{{ $navbarNotification['message'] }}</p>
                                </div>
                            </div>

                            @if ($navbarNotification['additional_count'] > 0)
                                <p class="navbar-notification-more">
                                    {{ $navbarNotification['additional_count'] === 1
                                        ? '1 more wishlisted game is on sale.'
                                        : $navbarNotification['additional_count'] . ' more wishlisted games are on sale.' }}
                                </p>
                            @endif
                        </div>
                    </li>
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
