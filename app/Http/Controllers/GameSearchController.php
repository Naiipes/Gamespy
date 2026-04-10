<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\CheapSharkService;
use App\Models\Game;
use App\Models\SearchBan;

class GameSearchController extends Controller
{
    private const SEARCH_CACHE_TTL_MINUTES = 3;
    private const SEARCH_RATE_LIMIT_MAX_ATTEMPTS = 9;
    private const SEARCH_RATE_LIMIT_DECAY_SECONDS = 60;
    private const SEARCH_BAN_SECONDS = 300;

    // Accepts a search query, reuses cached/burst results, and returns HTML or JSON payloads.
    public function search(Request $request, CheapSharkService $service)
    {
        $query = $request->q;

        if (!$query) {
            if ($request->wantsJson()) {
                return response()->json([]);
            }
            return view('search', ['games' => []]);
        }

        $normalizedQuery = CheapSharkService::normalizeSearchQuery((string) $query);

        if ($normalizedQuery === '') {
            if ($request->wantsJson()) {
                return response()->json([]);
            }

            return view('search', ['games' => []]);
        }

        $ip = $request->ip() ?? 'unknown';

        // Remove expired bans so the table only keeps active entries.
        SearchBan::query()->where('banned_until', '<=', now())->delete();

        $activeBan = SearchBan::query()
            ->where('ip', $ip)
            ->where('banned_until', '>', now())
            ->first();

        if ($activeBan) {
            $retryAfter = now()->diffInSeconds($activeBan->banned_until, false);
            $retryAfter = $retryAfter > 0 ? $retryAfter : 1;

            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'search_banned_temporarily',
                    'message' => 'You are banned for a while. Please try again later.',
                    'retry_after' => $retryAfter,
                ], 429);
            }

            return view('search', [
                'games' => [],
                'searchBusy' => true,
                'retryAfter' => $retryAfter,
                'searchBanMessage' => 'You are banned for a while. Please try again later.',
            ]);
        }

        $rateLimitKey = $this->searchRateLimitKey($request);

        // Minimal abuse guard: limit repeated search attempts per user/IP window.
        if (RateLimiter::tooManyAttempts($rateLimitKey, self::SEARCH_RATE_LIMIT_MAX_ATTEMPTS)) {
            $banSeconds = self::SEARCH_BAN_SECONDS;
            $user = $request->user();

            if ($user) {
                $banMinutes = max(1, (int) ($user->search_ban_minutes ?? 5));
                $banSeconds = $banMinutes * 60;

                // Increase the next ban duration by +1 minute after each ban.
                $user->search_ban_minutes = $banMinutes + 1;
                $user->saveQuietly();
            }

            $bannedUntil = now()->addSeconds($banSeconds);
            SearchBan::updateOrCreate(
                ['ip' => $ip],
                [
                    'user_id' => $user?->id,
                    'banned_until' => $bannedUntil,
                ],
            );

            $retryAfter = RateLimiter::availableIn($rateLimitKey);
            $banRetryAfter = now()->diffInSeconds($bannedUntil, false);
            $retryAfter = max(1, $banRetryAfter, $retryAfter);

            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'search_banned_temporarily',
                    'message' => 'You are banned for a while. Please try again later.',
                    'retry_after' => $retryAfter,
                ], 429);
            }

            return view('search', [
                'games' => [],
                'searchBusy' => true,
                'retryAfter' => $retryAfter,
                'searchBanMessage' => 'You are banned for a while. Please try again later.',
            ]);
        }

        RateLimiter::hit($rateLimitKey, self::SEARCH_RATE_LIMIT_DECAY_SECONDS);

        $cacheKey = 'search:' . $normalizedQuery;

        // 3-minute app-level cache to avoid repeating identical search processing.
        $games = Cache::get($cacheKey);

        if ($games === null) {
            $aggregatedDeals = $service->searchDealsAggregated($normalizedQuery);

            if (CheapSharkService::extractApiError($aggregatedDeals) === 'search_burst_timeout') {
                // Do not cache timeout responses; clients should retry after a short delay.
                if ($request->wantsJson()) {
                    return response()->json([
                        'error' => 'search_temporarily_busy',
                        'message' => 'Search is temporarily busy. Please retry shortly.',
                        'retry_after' => 1,
                    ], 503);
                }

                return view('search', [
                    'games' => [],
                    'searchBusy' => true,
                    'retryAfter' => 1,
                ]);
            }

            $results = CheapSharkService::normalizeDealList($aggregatedDeals);

            if (empty($results)) {
                $games = [];
            } else {
                $lowestDeals = collect($results)
                    ->groupBy(fn ($game) => $game['gameID'] ?? $game['internalName'] ?? $game['title'])
                    ->map(function ($deals) {
                        return CheapSharkService::selectPreferredDeal($deals->all()) ?? $deals->first();
                    })
                    ->values();

                $games = [];

                foreach ($lowestDeals as $game) {
                    $record = Game::updateOrCreate(
                        ['cheapshark_id' => $game['gameID']],
                        [
                            'title' => $game['title'],
                            'thumb' => $game['thumb'],
                            'cheapest_price' => $game['salePrice'],
                            'steamAppID' => $game['steamAppID'] ?? null,
                        ],
                    );

                    $record->dealID = $game['dealID'] ?? null;
                    $record->steamAppID = $game['steamAppID'] ?? null;
                    $record->salePrice = $game['salePrice'] ?? null;
                    $record->normalPrice = $game['normalPrice'] ?? null;
                    $record->savings = $game['savings'] ?? null;
                    $record->storeID = $game['storeID'] ?? null;
                    $games[] = $record;
                }
            }

            Cache::put($cacheKey, $games, now()->addMinutes(self::SEARCH_CACHE_TTL_MINUTES));
        }

        if ($request->wantsJson()) {
            return response()->json(
                collect($games)->map(function ($game) {
                    return [
                        'id' => $game->id,
                        'title' => $game->title,
                        'thumb' => $game->thumb,
                        'price' => $game->cheapest_price,
                    ];
                }),
            );
        }

        return view('search', compact('games'));
    }

    private function searchRateLimitKey(Request $request): string
    {
        $ip = $request->ip() ?? 'unknown';

        return sprintf('search:rate:ip:%s', $ip);
    }
}
