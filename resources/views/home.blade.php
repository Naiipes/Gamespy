@extends('layout.app')

@section('content')
    <div class="carousel-wrapper">
        <h1>Most Popular</h1>
        <button class="carousel-btn prev-btn" id="prevBtn">&#10094;</button>

        <div class="carousel-container" id="carouselContainer">
            <div class="carousel-track" id="carouselTrack">

                <div class="carousel-slide">
                    <img src="" alt="">
                    <div class="slide-overlay">
                        <div class="overlay-content">
                            <button class="view-deal-btn">View Deal</button>
                        </div>
                    </div>
                </div>

                <div class="carousel-slide current-slide">
                    <img src="" alt="">
                    <div class="slide-overlay">
                        <div class="overlay-content">
                            <button class="view-deal-btn">View Deal</button>
                        </div>
                    </div>
                </div>

                <div class="carousel-slide">
                    <img src="" alt="">
                    <div class="slide-overlay">
                        <div class="overlay-content">
                            <button class="view-deal-btn">View Deal</button>
                        </div>
                    </div>
                </div>

                <div class="carousel-slide">
                    <img src="" alt="">
                    <div class="slide-overlay">
                        <div class="overlay-content">
                            <button class="view-deal-btn">View Deal</button>
                        </div>
                    </div>
                </div>

                <div class="carousel-slide">
                    <img src="" alt="">
                    <div class="slide-overlay">
                        <div class="overlay-content">
                            <button class="view-deal-btn">View Deal</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <button class="carousel-btn next-btn" id="nextBtn">&#10095;</button>

        <div class="carousel-nav">
            <button class="nav-dot"></button>
            <button class="nav-dot active"></button>
            <button class="nav-dot"></button>
        </div>
    </div>

    <script src="{{ asset('js/carousel.js') }}"></script>
@endsection
