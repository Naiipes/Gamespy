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
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}">
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
                    <button class="dropdown-btn">Genres<span class="arrow"><x-arrow-down /></span></button>
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
                    </ul>
                </li>
                <li><a href="{{ route('wishlist') }}">Wishlist</a></li>
                @guest
                    <li><a class="logout-btn" href="{{ route('login') }}">Sign in</a></li>
                @endguest
                @auth
                    @php
                        $navbarNotifications = auth()
                            ->user()
                            ->wishlistNotifications()
                            ->with('game')
                            ->where('is_read', false)
                            ->latest()
                            ->take(5)
                            ->get();

                        $unreadNotificationCount = auth()
                            ->user()
                            ->wishlistNotifications()
                            ->where('is_read', false)
                            ->count();

                        $additionalNotificationCount = max($unreadNotificationCount - $navbarNotifications->count(), 0);
                    @endphp

                    <li class="navbar-notification" id="navbar-notification"
                        data-mark-read-url="{{ route('notifications.mark-read') }}">
                        <button class="notification-btn{{ $unreadNotificationCount > 0 ? ' has-unread' : '' }}"
                            id="navbar-notification-toggle" type="button" aria-expanded="false"
                            aria-controls="navbar-notification-dropdown">
                            <x-notification-icon />
                            @if ($unreadNotificationCount > 0)
                                <span class="notification-count" id="navbar-notification-count">
                                    {{ $unreadNotificationCount }}
                                </span>
                            @endif
                        </button>

                        <div class="navbar-notification-dropdown" id="navbar-notification-dropdown"
                            data-has-unread="{{ $unreadNotificationCount > 0 ? 'true' : 'false' }}" hidden>
                            @if ($navbarNotifications->isEmpty())
                                <p class="navbar-notification-empty">No new notifications</p>
                            @else
                                <div class="navbar-notification-list">
                                    @foreach ($navbarNotifications as $notification)
                                        @php
                                            $game = $notification->game;
                                            $typeLabel =
                                                $notification->type === 'target_price'
                                                    ? 'Target Price Hit'
                                                    : 'Wishlist Sale';
                                        @endphp
                                        <a class="navbar-notification-card" href="{{ route('wishlist') }}">
                                            <div class="navbar-notification-body">
                                                <div class="navbar-notification-meta">
                                                    <span class="navbar-notification-type">{{ $typeLabel }}</span>
                                                </div>

                                                <p class="navbar-notification-title">{{ $game?->title ?? 'Wishlist game' }}
                                                </p>
                                                <p class="navbar-notification-text">
                                                    @if ($notification->type === 'target_price')
                                                        Dropped to ${{ number_format((float) $notification->price, 2) }}
                                                        and matched your target of
                                                        ${{ number_format((float) $notification->target_price, 2) }}
                                                    @else
                                                        Now on sale for
                                                        ${{ number_format((float) $notification->price, 2) }}
                                                    @endif
                                                    @if (!empty($notification->store) && $notification->store !== 'Unknown')
                                                        at {{ $notification->store }}
                                                    @endif
                                                </p>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            @if ($additionalNotificationCount > 0)
                                <p class="navbar-notification-more">
                                    {{ $additionalNotificationCount === 1
                                        ? '1 more unread notifications'
                                        : $additionalNotificationCount . ' more unread notifications' }}
                                </p>
                            @endif
                        </div>
                    </li>
                    <li>
                        <form action="{{ route('logout') }}" method="POST" style="display: inline;">
                            @csrf
                            <button class="logout-btn" type="submit">Logout</button>
                        </form>
                    </li>
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
