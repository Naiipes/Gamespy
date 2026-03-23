@extends('layout.app')

@section('content')
        @guest
            <a class="guest-wishlist-message" href="{{ route('login') }}">Please log in to view your wishlist</a>
        @endguest
        
        @auth
            <div class="wishlist-wrapper">
                <h1>USER'S WISHLIST</h1>

            </div>
        @endauth

@endsection