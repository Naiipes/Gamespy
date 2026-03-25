@extends('layout.app')

@section('content')
    @php
        $stores = \App\Services\CheapSharkService::getAllStores();
    @endphp

    <div class="catalog-container">
        <h1 class="genre-title">{{ $genre }}</h1>
        
        <ul class="catalog-list">
            @foreach ($catalogGames as $game)
                @php
                    $savings = round(floatval($game['savings'] ?? 0));
                    $storeName = $stores[$game['storeID'] ?? ''] ?? 'View Deal';
                @endphp

                <a class="catalog-item" href="https://www.cheapshark.com/redirect?dealID={{ $game['dealID'] }}"
                    target="_blank">
                    <img src="https://cdn.akamai.steamstatic.com/steam/apps/{{ $game['steamAppID'] }}/capsule_616x353.jpg"
                        onerror="
                            if (!this.dataset.fallback1) {
                                this.dataset.fallback1 = true;
                                this.src='https://cdn.akamai.steamstatic.com/steam/apps/{{ $game['steamAppID'] }}/header.jpg';
                            } else if (!this.dataset.fallback2) {
                                this.dataset.fallback2 = true;
                                this.src='{{ $game['thumb'] }}';
                            }"
                        alt="{{ $game['title'] }}">

                    <div class="catalog-item-title">{{ $game['title'] }}</div>
                    <div class="catalog-item-pricing">
                        <div class="catalog-discount-badge">-{{ $savings }}%</div>
                        <div class="catalog-original-price">${{ $game['normalPrice'] }}</div>
                        <div class="catalog-sale-price">${{ $game['salePrice'] }}
                        </div>
                        <button class="wishlist-btn" data-cheapshark-id="{{ $game['gameID'] }}"
                            data-steam-app-id="{{ $game['steamAppID'] ?? '' }}" data-title="{{ $game['title'] }}"
                            data-thumb="{{ $game['thumb'] }}" data-price="{{ $game['salePrice'] }}"
                            onclick="event.preventDefault(); event.stopPropagation(); addToWishlist(this)">
                            <x-heart-btn />
                        </button>
                        @if (!empty($game['storeID']))
                            <img src="https://www.cheapshark.com/img/stores/logos/{{ max(((int) $game['storeID']) - 1, 0) }}.png"
                                style="width: 24px; height: auto; margin-left: auto;" alt="{{ $storeName }}"
                                onerror="this.style.display='none'">
                        @endif
                    </div>
                </a>
            @endforeach
        </ul>
    </div>
@endsection
