<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameSearchController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\GameDiscoveryController;
use App\Http\Controllers\GameDealController;
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

Route::get('/login', function () {
    return view('login');
})->name('login');


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

    Route::get('/notifications', function () {
        return view('notifications');
    });

    Route::get('/api/notifications',[NotificationController::class,'index']);

});