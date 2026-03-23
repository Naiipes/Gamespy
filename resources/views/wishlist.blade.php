@extends('layout.app')

@section('content')
    @guest
        <a class="guest-wishlist-message" href="{{ route('login') }}">Please log in to view your wishlist</a>
    @endguest

    @auth
        @php
            $stores = \App\Services\CheapSharkService::getAllStores();
        @endphp
        <div class="wishlist-wrapper">
            <h1>{{ auth()->user()->name }}'s Wishlist</h1>
            <div id="wishlist-results">
                @foreach ($wishlists as $item)
                    @php
                        $salePrice = $item->game->salePrice ?? $item->game->cheapest_price;
                        $normalPrice = $item->game->normalPrice ?? null;
                        $isDiscounted = is_numeric($salePrice) && is_numeric($normalPrice) && (float) $salePrice < (float) $normalPrice;
                        $savings = $isDiscounted && (float) $normalPrice > 0
                            ? round((1 - ((float) $salePrice / (float) $normalPrice)) * 100) : 0;
                        $storeName = $stores[$item->game->storeID ?? ''] ?? 'View Deal';
                    @endphp

                    <a class="result-card" href="{{ $item->game->dealID ? 'https://www.cheapshark.com/redirect?dealID=' . $item->game->dealID : '#' }}" target="_blank">
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
                                @if ($isDiscounted)
                                    <div class="result-pricing">
                                        <span class="catalog-discount-badge">-{{ $savings }}%</span>
                                        <div class="original-price">${{ $normalPrice }}</div>
                                        <div class="sale-price">${{ $salePrice }}</div>
                                    </div>
                                @else
                                    <div class="sale-price">${{ $item->game->cheapest_price }}</div>
                                @endif
                                @if (!empty($item->game->storeID))
                                    <img class="result-store-logo" src="https://www.cheapshark.com/img/stores/logos/{{ max(((int) $item->game->storeID) - 1, 0) }}.png"
                                        style="width: 35px; height: auto;" alt="{{ $storeName }}"
                                        onerror="this.style.display='none'">
                                @endif
                            </div>
                            <button class="wishlist-btn result-wishlist-btn in-wishlist" data-game-id="{{ $item->game->id }}"
                                onclick="event.preventDefault(); event.stopPropagation(); removeFromWishlist(this)">
                                <x-heart-btn />
                            </button>
                        </div>
                    </a>
                @endforeach
                <p id="wishlist-empty-message" class="wishlist-empty" @if ($wishlists->isNotEmpty()) hidden @endif>
                    Your wishlist is empty!
                </p>
            </div>
        </div>
        <script src="{{ asset('js/wishlist.js?v=') . time() }}"></script>
    @endauth
@endsection
