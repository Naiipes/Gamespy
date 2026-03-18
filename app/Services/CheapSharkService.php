<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CheapSharkService
{
    public const STORES = [
        1 => 'Steam',
        2 => 'GamersGate',
        3 => 'Green Man Gaming',
        4 => 'Amazon',
        5 => 'Newegg',
        6 => 'GOG',
        7 => 'Origin',
        8 => 'Uplay',
        9 => 'Epic Games Store',
        10 => 'Direct2Drive',
        11 => 'Humble Store',
        12 => 'Fanatical',
        13 => 'Voidu',
        15 => 'Gamesplanet',
        17 => 'Humble Store',
        18 => 'Wingamestore',
        21 => 'WinGameStore',
        23 => 'GameBillet',
        25 => 'itch.io',
        27 => 'Epic Games Store',
        28 => 'Amazon',
        29 => 'GamesPlanet US',
        35 => 'IndieGala',
    ];

    public function getStoreName(int $storeId): string
    {
        return self::STORES[$storeId] ?? "Store #{$storeId}";
    }

    public static function getAllStores(): array
    {
        return self::STORES;
    }

    public function search($query)
    {

        $response = Http::get(
            "https://www.cheapshark.com/api/1.0/games",
            [
                "title"=>$query,
                "limit"=>20
            ]
        );

        return $response->json();

    }

    public function searchDeals($query)
    {
        $response = Http::get(
            "https://www.cheapshark.com/api/1.0/deals",
            [
                "title" => $query,
                "pageSize" => 20
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