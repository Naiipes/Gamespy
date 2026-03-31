<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\GameSearchController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GenreController;

/* Navbar/Home */
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get("/search",[GameSearchController::class,"search"])->name('search');
Route::get('/genres/{genre}', [GenreController::class, 'show'])->name('genres.show');

/* Authentication */
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);

/* Wishlist */
Route::post('/wishlist',[WishlistController::class,'store']);
Route::get('/wishlist',[WishlistController::class,'index'])->name('wishlist');
Route::get('/api/wishlist/ids',[WishlistController::class,'gameIds']);
Route::patch('/wishlist/game/{game_id}/target-price',[WishlistController::class,'updateTargetPrice']);
Route::delete('/wishlist/game/{game_id}',[WishlistController::class,'deleteGame']);
Route::post('/notifications/mark-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-read');

