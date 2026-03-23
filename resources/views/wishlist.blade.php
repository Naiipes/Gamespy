@extends('layout.app')

@section('content')
    @guest
        <a class="guest-wishlist-message" href="{{ route('login') }}">Please log in to view your wishlist</a>
    @endguest

    @auth
        <div class="wishlist-wrapper">
            <h1>{{ auth()->user()->name }}'s Wishlist</h1>
            <div id="wishlist-results">
                @forelse ($wishlists as $item)
                    <a class="result-card" href="" target="_blank">
                        <img src="https://cdn.akamai.steamstatic.com/steam/apps/{{ $item->game->steamAppID }}/capsule_616x353.jpg"
                            onerror="
                                if (!this.dataset.fallback1) {
                                    this.dataset.fallback1 = true;
                                    this.src='https://cdn.akamai.steamstatic.com/steam/apps/{{ $item->game->steamAppID }}/header.jpg';
                                } else if (!this.dataset.fallback2) {
                                    this.dataset.fallback2 = true;
                                    this.src='{{ $item->game->thumb }}';
                                }"
                            alt="{{ $item->game->title }}">
                        <div class="result-info">
                            <div class="result-main">
                                <h2 class="result-title">{{ $item->game->title }}</h2>
                                <div class="sale-price">${{ $item->game->cheapest_price }}</div>
                            </div>
                            <button class="wishlist-btn result-wishlist-btn in-wishlist" data-game-id="{{ $item->game->id }}"
                                onclick="event.preventDefault(); event.stopPropagation(); removeFromWishlist(this)">
                                <x-heart-btn />
                            </button>
                        </div>
                    </a>
                @empty
                    <p class="wishlist-empty">Your wishlist is empty!</p>
                @endforelse
            </div>
        </div>
        <script src="{{ asset('js/wishlist.js?v=') . time() }}"></script>
    @endauth
@endsection
