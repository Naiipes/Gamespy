
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameSearchController;
use App\Http\Controllers\WishlistController;

Route::get('/', function () {
    return view('home');
});
Route::get("/search",[GameSearchController::class,"search"]);

Route::post("/wishlist",[WishlistController::class,"store"])
    ->middleware("auth");
