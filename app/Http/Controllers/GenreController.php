<?php

namespace App\Http\Controllers;

use App\Services\GameDiscoveryService;
use Illuminate\Support\Str;

class GenreController extends Controller
{
    // Converts a genre slug to cache key form, fetches cached games, and renders the genre page.
    public function show(string $genre, GameDiscoveryService $service)
    {
        // Route slugs use dashes, while cached genre keys are stored as lowercase words.
        $genreKey = Str::of($genre)->replace('-', ' ')->lower()->toString();

        $catalogGames = $service->genre($genreKey);


        return view('genres', [
            'genre' => Str::of($genre)->replace('-', ' ')->title(),
            'catalogGames' => $catalogGames,
        ]);
    }
}
