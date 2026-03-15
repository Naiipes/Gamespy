<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\GameSearchController;
use App\Http\Controllers\GameDiscoveryController;
use App\Http\Controllers\GameDealController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\NotificationController;
use App\Services\GameDiscoveryService;
use Illuminate\Support\Facades\Route;

// Temp route to build cache
Route::get('/dev/build-cache', function (GameDiscoveryService $service) {
    set_time_limit(300);

    $service->buildDailyCache();

    $counts = \Illuminate\Support\Facades\DB::table('game_recommendations')
        ->get(['type', 'payload'])
        ->mapWithKeys(fn ($row) => [$row->type => count(json_decode($row->payload, true) ?: [])])
        ->all();

    return response()->json([
        'message' => 'Game discovery cache rebuilt.',
        'counts' => $counts,
    ]);
});


Route::get('/', [HomeController::class, 'index'])->name('home');

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


Route::middleware('auth')->group(function () {

    Route::post('/wishlist',[WishlistController::class,'store']);
    Route::get('/wishlist',[WishlistController::class,'index']);
    Route::delete('/wishlist/game/{game_id}',[WishlistController::class,'deleteGame']);

    Route::get('/notifications', function () {
        return view('notifications');
    });

    Route::get('/api/notifications',[NotificationController::class,'index']);

}); 


Route::get('/games/popular', [GameDiscoveryController::class, 'popular']);
Route::get('/games/discounts', [GameDiscoveryController::class, 'discounts']);
Route::get('/games/free', [GameDiscoveryController::class, 'free']);
Route::get('/games/genre/{genre}', [GameDiscoveryController::class, 'genre']);
Route::get('/games/aaa', [GameDiscoveryController::class, 'aaa']);
Route::get('/games/recommend', [GameDiscoveryController::class, 'recommend']);
