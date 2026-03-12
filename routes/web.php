<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\GameSearchController;
use App\Http\Controllers\GameDiscoveryController;
use App\Http\Controllers\GameDealController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\NotificationController;


Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/genres', function () {
    return view('genres');
})->name('genres');

Route::get('/trending', function () {
    return view('trending');
})->name('trending');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);

Route::get("/search",[GameSearchController::class,"search"])->name('search');

/* API */

Route::get("/api/popular",[GameDiscoveryController::class,"popular"]);
Route::get("/api/discounts",[GameDiscoveryController::class,"discounts"]);
Route::get("/api/free",[GameDiscoveryController::class,"free"]);
Route::get("/api/search",[GameSearchController::class,"search"]);
Route::get("/api/game/{id}/deals",[GameDealController::class,"show"]);


/* Auth routes */

Route::middleware('auth')->group(function () {

    Route::post('/wishlist',[WishlistController::class,'store']);
    Route::get('/wishlist',[WishlistController::class,'index']);
    Route::delete('/wishlist/game/{game_id}',[WishlistController::class,'deleteGame']);

    Route::get('/notifications', function () {
        return view('notifications');
    });

    Route::get('/api/notifications',[NotificationController::class,'index']);

});