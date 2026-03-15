@extends('layout.app')

@section('content')
    <div class="carousel-wrapper">
        <h1>Trending</h1>
        <button class="carousel-btn prev-btn" id="prevBtn">&#10094;</button>

        <div class="carousel-container" id="carouselContainer">
            <div class="carousel-track" id="carouselTrack">

                @foreach ($popularGames as $index => $game)
                    <div class="carousel-slide {{ $index === 0 ? 'current-slide' : '' }}">
                        <img src="https://cdn.akamai.steamstatic.com/steam/apps/{{ $game['steamAppID'] }}/header.jpg"
                            onerror="this.src='{{ $game['thumb'] }}'" alt="{{ $game['title'] }}">
                        <div class="slide-overlay">
                            <a href="https://www.cheapshark.com/redirect?dealID={{ $game['dealID'] }}" target="_blank">
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

    <script src="{{ asset('js/carousel.js') }}"></script>
@endsection
