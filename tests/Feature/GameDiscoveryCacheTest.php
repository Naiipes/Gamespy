<?php

use App\Services\GameDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('keeps the existing discovery cache when CheapShark returns an error payload', function () {
    $existingRecommend = [[
        'gameID' => '123',
        'title' => 'Existing Game',
        'steamAppID' => '456',
        'salePrice' => '9.99',
    ]];

    $existingAaa = [[
        'gameID' => '789',
        'title' => 'Existing AAA',
        'steamAppID' => '999',
        'salePrice' => '19.99',
    ]];

    DB::table('game_recommendations')->insert([
        [
            'type' => 'recommend',
            'payload' => json_encode($existingRecommend),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ],
        [
            'type' => 'aaa',
            'payload' => json_encode($existingAaa),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ],
    ]);

    Http::fake([
        'https://www.cheapshark.com/api/1.0/deals*' => Http::response([
            'error' => 'You are being temporarily blocked due to rate limiting.',
        ]),
    ]);

    $result = app(GameDiscoveryService::class)->buildDailyCache();

    expect($result)->toBeFalse()
        ->and(json_decode(DB::table('game_recommendations')->where('type', 'recommend')->value('payload'), true))
        ->toBe($existingRecommend)
        ->and(json_decode(DB::table('game_recommendations')->where('type', 'aaa')->value('payload'), true))
        ->toBe($existingAaa);
});

it('returns the search page successfully when CheapShark returns an error payload', function () {
    Http::fake([
        'https://www.cheapshark.com/api/1.0/deals*' => Http::response([
            'error' => 'You are being temporarily blocked due to rate limiting.',
        ]),
    ]);

    $this->get('/search?q=rogue legacy')->assertOk();
});
