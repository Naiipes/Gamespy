@extends('layout.app')

@section('content')
    <div class="results-wrapper">
        <h1>Search results for "{{ request('q') }}"</h1>
        <div id="results">
            @foreach ($games as $game)
            
                <a class="result-card" href="https://www.cheapshark.com/redirect?dealID={{ $game['dealID'] }}" target="_blank">
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
                    <div class="result-info">
                        <h2 class="result-title">{{ $game['title'] }}</h2>
                        @if ((float) $game['salePrice'] < (float) $game['normalPrice'])
                            <div class="original-price">${{ $game['normalPrice'] }}</div>
                            <div class="sale-price">${{ $game['salePrice'] }}</div>
                        @else
                            <div class="result-price">${{ $game['cheapest_price'] }}</div>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@endsection
