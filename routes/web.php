<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameSearchController;
use App\Http\Controllers\WishlistController;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/genres', function () {
    return view('genres');
})->name('genres');

Route::get('/trending', function () {
    return view('trending');
})->name('trending');

Route::get('/login', function () {
    return view('login');
})->name('login');

Route::get("/search",[GameSearchController::class,"search"])->name('search');

Route::post("/wishlist",[WishlistController::class,"store"])
    ->middleware("auth");
