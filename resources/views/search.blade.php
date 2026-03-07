@extends('layout.app')

@section('content')
    <div id="results">
        @foreach ($games as $game)
            <div>
                <img src="{{ $game['thumb'] }}" width="300" alt="{{ $game['external'] }}">
                {{ $game['external'] }} - ${{ $game['cheapest'] }}
            </div>
        @endforeach
    </div>
@endsection
