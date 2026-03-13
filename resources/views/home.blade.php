@extends('layout.app')

@section('content')
    <div class="carousel-wrapper">
        <h1>Trending</h1>
        <button class="carousel-btn prev-btn" id="prevBtn">&#10094;</button>

        <div class="carousel-container" id="carouselContainer">
            <div class="carousel-track" id="carouselTrack">

                <div class="carousel-slide">
                    <img src="https://i.ytimg.com/vi/T54OWinnymM/maxresdefault.jpg" alt="">
                    <div class="slide-overlay">
                        <button class="view-deal-btn">View Deal</button>
                    </div>
                </div>
                <div class="carousel-slide current-slide">
                    <img src="https://cdn1.epicgames.com/b30b6d1b4dfd4dcc93b5490be5e094e5/offer/RDR2476298253_Epic_Games_Wishlist_RDR2_2560x1440_V01-2560x1440-2a9ebe1f7ee202102555be202d5632ec.jpg" alt="">
                    <div class="slide-overlay">
                        <button class="view-deal-btn">View Deal</button>                        
                    </div>
                </div>
                <div class="carousel-slide">
                    <img src="https://www.nintendo.com/eu/media/images/assets/nintendo_switch_games/apexlegends/16x9_ApexLegends_image1600w.jpg" alt="">
                    <div class="slide-overlay">
                        <button class="view-deal-btn">View Deal</button>
                    </div>
                </div>
                <div class="carousel-slide">
                    <img src="" alt="">
                    <div class="slide-overlay">
                        <button class="view-deal-btn">View Deal</button>
                    </div>
                </div>
                <div class="carousel-slide">
                    <img src="" alt="">
                    <div class="slide-overlay">
                        <button class="view-deal-btn">View Deal</button>
                    </div>
                </div>

            </div>
        </div>

        <button class="carousel-btn next-btn" id="nextBtn">&#10095;</button>

        <div class="carousel-nav">
            <button class="nav-dot"></button>
            <button class="nav-dot active"></button>
            <button class="nav-dot"></button>
            <button class="nav-dot"></button>
            <button class="nav-dot"></button>
        </div>
    </div>

    <script src="{{ asset('js/carousel.js') }}"></script>
@endsection
