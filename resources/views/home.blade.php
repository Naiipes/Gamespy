@extends('layout.app')

@section('content')
    <div class="carousel-wrapper">
        <h1>Trending</h1>
        <p class="carousel-current-title" id="currentSlideTitle">
            {{ $popularGames[0]['title'] ?? $popularGames[0]['gameName'] ?? 'Unknown title' }}
        </p>
        <button class="carousel-btn prev-btn" id="prevBtn">&#10094;</button>

        <div class="carousel-container" id="carouselContainer">
            <div class="carousel-track" id="carouselTrack">

                @foreach ($popularGames as $index => $game)
                    <div class="carousel-slide {{ $index === 0 ? 'current-slide' : '' }}"
                        data-title="{{ $game['title'] ?? $game['gameName'] ?? 'Unknown title' }}">
                        @if (!empty($game['storeID']))
                            <div class="store-badge" title="Store ID: {{ $game['storeID'] }}">
                                <img src="https://www.cheapshark.com/img/stores/logos/{{ max(((int) $game['storeID']) - 1, 0) }}.png"
                                    alt="Store logo" onerror="this.parentElement.style.display='none'">
                            </div>
                        @endif
                        <img src="https://cdn.akamai.steamstatic.com/steam/apps/{{ $game['steamAppID'] }}/capsule_616x353.jpg"
                            onerror="if (this.dataset.fallback !== '1') { this.dataset.fallback = '1'; this.src = 'https://cdn.akamai.steamstatic.com/steam/apps/{{ $game['steamAppID'] }}/header.jpg'; } else { this.src = '{{ $game['thumb'] }}'; }"
                            alt="{{ $game['title'] }}">
                        <div class="slide-overlay">
                            <a href="https://www.cheapshark.com/redirect?dealID={{ $game['dealID'] }}" target="_blank" rel="noopener noreferrer">
                                <button class="view-deal-btn">
                                    VIEW DEAL | ${{ $game['salePrice'] }}
                                </button>
                            </a>
                        </div>
                    </div>
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

    <script src="{{ asset('js/carousel.js?v=') . time() }}"></script>
@endsection
