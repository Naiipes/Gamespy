<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CheapSharkService
{

    public function search($query)
    {

        $response = Http::get(
            "https://www.cheapshark.com/api/1.0/games",
            [
                "title"=>$query,
                "limit"=>10
            ]
        );

        return $response->json();

    }

    public function deals($gameId)
    {

        $response = Http::get(
            "https://www.cheapshark.com/api/1.0/games",
            [
                "id"=>$gameId
            ]
        );

    return $response->json();

    }

}