@extends('layout.app')

@section('content')
    <div class="wishlist-wrapper">
        @guest
            <a class="wishlist-message" href="{{ route('login') }}">Please log in to view your wishlist</a>
        @endguest
        
        @auth
            <p>Wishlist</p>
        @endauth
    </div>

@endsection