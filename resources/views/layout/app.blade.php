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

    <script>
    // ページ読み込み時: WishlistにあるゲームのハートをすでにIN状態にする
    async function initWishlistState() {
        const res = await fetch('/api/wishlist/ids', {
            headers: { 'Accept': 'application/json' }
        }).catch(() => null);
        if (!res || !res.ok) return;

        const list = await res.json().catch(() => []);
        if (!list.length) return;

        const byGameId      = new Set(list.map(i => String(i.game_id)));
        const byCheapshark  = new Set(list.map(i => String(i.cheapshark_id)).filter(Boolean));
        // cheapshark_id → game_id の逆引きマップ
        const csToGameId    = Object.fromEntries(
            list.filter(i => i.cheapshark_id).map(i => [String(i.cheapshark_id), String(i.game_id)])
        );

        document.querySelectorAll('.wishlist-btn').forEach(btn => {
            const gid = btn.dataset.gameId;
            const cid = btn.dataset.cheapsharkId;
            if ((gid && byGameId.has(String(gid))) || (cid && byCheapshark.has(String(cid)))) {
                btn.classList.add('in-wishlist');
                // 削除時に使う game_id をセット
                if (!gid && cid && csToGameId[cid]) {
                    btn.dataset.resolvedGameId = csToGameId[cid];
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initWishlistState);

    async function addToWishlist(btn) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrf) return;

        const gameId      = btn.dataset.gameId;
        const cheapsharkId = btn.dataset.cheapsharkId;
        const price       = parseFloat(btn.dataset.price || '0');

        // if already in wishlist, send delete request
        if (btn.classList.contains('in-wishlist')) {
            const id = btn.dataset.resolvedGameId || gameId;
            if (!id) return;
            const res = await fetch(`/wishlist/game/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            }).catch(() => null);
            if (res && res.ok) {
                btn.classList.remove('in-wishlist');
                delete btn.dataset.resolvedGameId;
            }
            return;
        }

        // add to wishlist
        let body;
        if (gameId) {
            body = { game_id: parseInt(gameId), target_price: price };
        } else if (cheapsharkId) {
            body = {
                cheapshark_id: cheapsharkId,
                title:         btn.dataset.title || '',
                thumb:         btn.dataset.thumb || '',
                target_price:  price
            };
        } else {
            return;
        }

        const res = await fetch('/wishlist', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN':  csrf,
                'Content-Type':  'application/json',
                'Accept':        'application/json'
            },
            body: JSON.stringify(body)
        }).catch(() => null);

        if (!res) return;

        if (res.status === 201 || res.status === 409) {
            btn.classList.add('in-wishlist');
            const data = await res.json().catch(() => null);
            if (data?.game_id) btn.dataset.resolvedGameId = data.game_id;
        } else if (res.status === 401) {
            window.location.href = '/login';
        }
    }
    </script>
</body>

</html>
