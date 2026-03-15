<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\GameSearchController;
use App\Http\Controllers\GameDiscoveryController;
use App\Http\Controllers\GameDealController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// Temp route to build cache
Route::get('/dev/build-cache', function() {
    set_time_limit(300);

    $deals = \Illuminate\Support\Facades\Http::get('https://www.cheapshark.com/api/1.0/deals', [
        'sortBy'     => 'DealRating',
        'pageSize'   => 60, 
        'pageNumber' => 0,
    ])->json();

    $deals = collect($deals)
        ->unique('title')          
        ->filter(fn($d) => !empty($d['steamAppID'])) 
        // only keep games with Steam images to show on Carousel (otherwise it looks bad)
        ->take(20)                                
        ->values()
        ->toArray();
    \Illuminate\Support\Facades\DB::table('game_recommendations')->updateOrInsert(
        ['type' => 'recommend'],
        [
            'payload'    => json_encode(array_values($deals)),
            'updated_at' => now(),
            'created_at' => now(),
        ]
    );

    return 'Saved ' . count($deals) . ' games';
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
