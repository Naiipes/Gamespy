<?php

namespace App\Http\Controllers;

use App\Services\GameDiscoveryService;
use Illuminate\Support\Str;

class GenreController extends Controller
{
    public function show(string $genre, GameDiscoveryService $service)
    {
        $genreKey = Str::of($genre)->replace('-', ' ')->lower()->toString();

        $catalogGames = $service->genre($genreKey);


        return view('genres', [
            'genre' => Str::of($genre)->replace('-', ' ')->title(),
            'catalogGames' => $catalogGames,
        ]);
    }
}
