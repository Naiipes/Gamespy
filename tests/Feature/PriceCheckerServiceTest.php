<?php

use App\Models\Game;
use App\Models\Notification;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\PriceCheckerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeCheapSharkGamePriceResponse(float $price, float $retailPrice, string $storeId = '1'): void
{
    Http::fake([
        'https://www.cheapshark.com/api/1.0/games*' => Http::response([
            'deals' => [[
                'price' => number_format($price, 2, '.', ''),
                'retailPrice' => number_format($retailPrice, 2, '.', ''),
                'storeID' => $storeId,
            ]],
        ]),
    ]);
}

function createWishlistForPriceChecks(array $wishlistOverrides = []): Wishlist
{
    $user = User::factory()->create();

    $game = Game::create([
        'cheapshark_id' => 'game-123',
        'title' => 'Test Game',
        'thumb' => 'thumb.jpg',
        'cheapest_price' => 29.99,
        'steamAppID' => '12345',
    ]);

    return Wishlist::create(array_merge([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'target_price' => 10,
        'notifications_enabled' => true,
        'notify_by_email' => false,
        'use_target_price' => false,
        'was_on_sale_last_check' => false,
        'target_notification_sent' => false,
    ], $wishlistOverrides));
}

it('creates a target-price notification when target mode is enabled and the price is below target', function () {
    fakeCheapSharkGamePriceResponse(0, 9.99);

    $wishlist = createWishlistForPriceChecks([
        'target_price' => 0,
        'use_target_price' => true,
        'was_on_sale_last_check' => null,
    ]);

    app(PriceCheckerService::class)->checkPrices();

    expect(Notification::count())->toBe(1);

    $notification = Notification::first();

    expect($notification->type)->toBe('target_price')
        ->and((float) $notification->price)->toBe(0.0)
        ->and((float) $notification->target_price)->toBe(0.0);

    expect($wishlist->fresh()->target_notification_sent)->toBeTrue();
});

it('does not create a target-price notification when the price is above the target', function () {
    fakeCheapSharkGamePriceResponse(14.99, 19.99);

    $wishlist = createWishlistForPriceChecks([
        'target_price' => 10,
        'use_target_price' => true,
        'was_on_sale_last_check' => null,
        'target_notification_sent' => true,
    ]);

    app(PriceCheckerService::class)->checkPrices();

    expect(Notification::count())->toBe(0);
    expect($wishlist->fresh()->target_notification_sent)->toBeFalse();
});

it('creates one sale notification when a game transitions from not on sale to on sale', function () {
    fakeCheapSharkGamePriceResponse(4.99, 19.99);

    $wishlist = createWishlistForPriceChecks([
        'use_target_price' => false,
        'was_on_sale_last_check' => false,
    ]);

    app(PriceCheckerService::class)->checkPrices();
    app(PriceCheckerService::class)->checkPrices();

    expect(Notification::count())->toBe(1);

    $notification = Notification::first();

    expect($notification->type)->toBe('sale');
    expect($wishlist->fresh()->was_on_sale_last_check)->toBeTrue();
});
