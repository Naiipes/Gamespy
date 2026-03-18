@extends('layout.app')

@section('content')
    {{-- Carousel Start --}}
    <div class="carousel-wrapper">
        <h1>BEST DEALS</h1>

        <button class="carousel-btn prev-btn" id="prevBtn">&#10094;</button>
        <div class="carousel-container" id="carouselContainer">
            <div class="carousel-track" id="carouselTrack">

                @foreach ($popularGames as $index => $game)
                    @php
                        $savings = round(floatval($game['savings'] ?? 0));
                        $stores = [
                            '1' => 'Steam',
                            '2' => 'GamersGate',
                            '3' => 'GreenManGaming',
                            '7' => 'GOG',
                            '8' => 'Origin',
                            '11' => 'Humble Store',
                            '13' => 'Fanatical',
                            '15' => 'Gamesplanet',
                            '21' => 'WinGameStore',
                            '23' => 'GameBillet',
                            '25' => 'Voidu',
                            '27' => 'Epic Games',
                            '28' => 'Amazon',
                            '29' => 'GamesPlanet US',
                            '35' => 'IndieGala',
                        ];
                        $storeName = $stores[$game['storeID'] ?? ''] ?? 'View Deal';
                    @endphp

                    <a class="carousel-slide {{ $index === 0 ? 'current-slide' : '' }}"
                        data-title="{{ $game['title'] ?? 'Unknown title' }}"
                        href="https://www.cheapshark.com/redirect?dealID={{ $game['dealID'] }}" target="_blank">

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

                        <div class="slide-title">{{ $game['title'] }}</div>

                        <div class="slide-info-bar">
                            <div class="slide-pricing">
                                <div class="discount-badge">-{{ $savings }}%</div>
                                <div class="original-price">${{ $game['normalPrice'] }}</div>
                                <div class="sale-price">${{ $game['salePrice'] }}</div>
                            </div>
                            @if (!empty($game['storeID']))
                                <img src="https://www.cheapshark.com/img/stores/logos/{{ max(((int) $game['storeID']) - 1, 0) }}.png"
                                    style="width: 35px; height: auto;" alt="{{ $storeName }}"
                                    onerror="this.style.display='none'">
                            @endif
                        </div>
                    </a>
                @endforeach

            </div>
        </div>
        <button class="carousel-btn next-btn" id="nextBtn">&#10095;</button>

        <div class="carousel-nav">
            @foreach ($popularGames as $index => $game)
                <button class="nav-dot {{ $index === 0 ? 'active' : '' }}"></button>
            @endforeach
        </div>
    </div>
    {{-- Carousel End --}}

    <div class="catalog-container">
        <ul class="catalog-list">
            @foreach ($catalogGames as $game)
                @php
                    $savings = round(floatval($game['savings'] ?? 0));
                    $stores = [
                        '1' => 'Steam',
                        '2' => 'GamersGate',
                        '3' => 'GreenManGaming',
                        '7' => 'GOG',
                        '8' => 'Origin',
                        '11' => 'Humble Store',
                        '13' => 'Fanatical',
                        '15' => 'Gamesplanet',
                        '21' => 'WinGameStore',
                        '23' => 'GameBillet',
                        '25' => 'Voidu',
                        '27' => 'Epic Games',
                        '28' => 'Amazon',
                        '29' => 'GamesPlanet US',
                        '35' => 'IndieGala',
                    ];
                    $storeName = $stores[$game['storeID'] ?? ''] ?? 'View Deal';
                @endphp

                <a class="catalog-item" href="https://www.cheapshark.com/redirect?dealID={{ $game['dealID'] }}" target="_blank">
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

                        
                        <div class="catalog-item-title">{{ $game['title'] }} </div>
                        <div class="catalog-item-pricing">
                            <div class="catalog-discount-badge">-{{ $savings }}%</div>
                            <div class="catalog-original-price">${{ $game['normalPrice'] }}</div>
                            <div class="catalog-sale-price">${{ $game['salePrice'] }}</div>
                            @if (!empty($game['storeID']))
                                <img src="https://www.cheapshark.com/img/stores/logos/{{ max(((int) $game['storeID']) - 1, 0) }}.png"
                                    style="width: 24px; height: auto; margin-left: auto;" 
                                    alt="{{ $storeName }}"
                                    onerror="this.style.display='none'">
                            @endif
                        </div>
                </a>
            @endforeach
        </ul>
    </div>

    <script src="{{ asset('js/carousel.js?v=') . time() }}"></script>
@endsection
