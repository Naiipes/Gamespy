<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class CheapSharkService
{
    public const STORES = [
        1 => 'Steam',
        2 => 'GamersGate',
        3 => 'GreenManGaming',
        4 => 'Amazon',
        5 => 'GameStop',
        6 => 'Direct2Drive',
        7 => 'GOG',
        8 => 'Origin',
        9 => 'Get Games',
        10 => 'Shiny Loot',
        11 => 'Humble Store',
        12 => 'Desura',
        13 => 'Uplay',
        14 => 'IndieGameStand',
        15 => 'Fanatical',
        16 => 'Gamesrocket',
        17 => 'Games Republic',
        18 => 'SilaGames',
        19 => 'Playfield',
        20 => 'ImperialGames',
        21 => 'WinGameStore',
        22 => 'FunStockDigital',
        23 => 'GameBillet',
        24 => 'Voidu',
        25 => 'Epic Games Store',
        26 => 'Razer Game Store',
        27 => 'Gamesplanet',
        28 => 'Gamesload',
        29 => '2Game',
        30 => 'IndieGala',
        31 => 'Blizzard Shop',
        32 => 'AllYouPlay',
        33 => 'DLGamer',
        34 => 'Noctre',
        35 => 'DreamGame',
    ];

    public function getStoreName(int $storeId): string
    {
        return self::resolveStoreName($storeId);
    }

    public static function getAllStores(): array
    {
        return self::STORES;
    }

    public static function resolveStoreName($storeId): string
    {
        $storeId = self::normalizeStoreId($storeId);

        if ($storeId === null) {
            return 'Unknown';
        }

        return self::STORES[$storeId] ?? "Store #{$storeId}";
    }

    public static function selectPreferredDeal(array $deals): ?array
    {
        return collect(self::normalizeDealList($deals))
            ->filter(fn ($deal) => is_array($deal))
            ->sort(fn (array $left, array $right) => self::compareDeals($left, $right))
            ->first();
    }

    public static function normalizeDealList($payload): array
    {
        if (!is_array($payload) || !array_is_list($payload)) {
            return [];
        }

        return array_values(array_filter($payload, fn ($deal) => is_array($deal)));
    }

    public static function extractApiError($payload): ?string
    {
        if (!is_array($payload)) {
            return null;
        }

        $error = $payload['error'] ?? null;

        return is_string($error) && $error !== '' ? $error : null;
    }

    public static function client(): PendingRequest
    {
        $request = Http::timeout(15);

        // Local PHP setups on Windows can fail SSL verification without a CA bundle.
        if (app()->environment('local')) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    public function search($query)
    {

        $response = self::client()->get(
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
        $response = self::client()->get(
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

        $response = self::client()->get(
            "https://www.cheapshark.com/api/1.0/games",
            [
                "id"=>$gameId
            ]
        );

        return $response->json();
    }

    private static function compareDeals(array $left, array $right): int
    {
        $priceComparison = self::dealPrice($left) <=> self::dealPrice($right);

        if ($priceComparison !== 0) {
            return $priceComparison;
        }

        $savingsComparison = self::dealSavings($right) <=> self::dealSavings($left);

        if ($savingsComparison !== 0) {
            return $savingsComparison;
        }

        $storeComparison = self::normalizeStoreId($left['storeID'] ?? null, PHP_INT_MAX)
            <=> self::normalizeStoreId($right['storeID'] ?? null, PHP_INT_MAX);

        if ($storeComparison !== 0) {
            return $storeComparison;
        }

        return strcmp((string) ($left['dealID'] ?? ''), (string) ($right['dealID'] ?? ''));
    }

    private static function dealPrice(array $deal): float
    {
        return (float) (
            $deal['price']
            ?? $deal['salePrice']
            ?? $deal['cheapest']
            ?? INF
        );
    }

    private static function dealSavings(array $deal): float
    {
        if (isset($deal['savings'])) {
            return (float) $deal['savings'];
        }

        $price = self::dealPrice($deal);
        $retail = (float) ($deal['retailPrice'] ?? $deal['normalPrice'] ?? $price);

        if ($retail <= 0 || $price >= $retail) {
            return 0.0;
        }

        return (1 - ($price / $retail)) * 100;
    }

    private static function normalizeStoreId($storeId, ?int $default = null): ?int
    {
        if ($storeId === null || $storeId === '') {
            return $default;
        }

        return (int) $storeId;
    }
}
