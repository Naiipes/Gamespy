@extends('layouts.app')

@section('content')
    <div id="results">
        @foreach ($games as $game)
            <div>
                <img src="{{ $game->thumb }}" width="300" alt="{{ $game->title }}">
                {{ $game->title }} - ${{ $game->cheapest_price }}
            </div>
        @endforeach
    </div>
@endsection
