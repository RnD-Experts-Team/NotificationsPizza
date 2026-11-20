<?php

namespace App\Services\Announcement;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proxies the Giphy API so the frontend can offer a WhatsApp-style trending
 * /search GIF picker without ever seeing the provider API key. Responses are
 * normalized so swapping to a different provider later (e.g. Tenor) only
 * means changing this one class, not the API contract this project exposes.
 *
 * Caching matters here for a reason specific to this feature: Giphy's rate
 * limit is per API KEY, not per end user — one shared quota for the whole
 * app regardless of how many people are using it. "Trending" in particular
 * returns the same result for everyone, so without caching, every user
 * opening the picker spends a slice of that one shared quota to fetch a
 * result that's already been fetched for someone else seconds earlier.
 * Caching converts "N users = N upstream calls" into "a handful of upstream
 * calls per cache window, no matter how many users there are" — the only
 * fix that actually removes user-count as a variable in whether this holds
 * up, rather than just raising the threshold at which it breaks.
 */
class GifProviderService
{
    private const TRENDING_TTL_SECONDS = 300; // same result for everyone; refresh every 5 min
    private const SEARCH_TTL_SECONDS = 600; // popular search terms repeat a lot
    private const FAILURE_TTL_SECONDS = 60; // short — also doubles as a circuit breaker while Giphy is down/rate-limited, so a 429 doesn't get hammered into a worse 429

    public function trending(int $limit = 24, int $offset = 0): array
    {
        return $this->cached("gif:trending:{$limit}:{$offset}", self::TRENDING_TTL_SECONDS, fn () => $this->normalize($this->request('trending', [
            'limit' => $limit,
            'offset' => $offset,
        ])));
    }

    public function search(string $query, int $limit = 24, int $offset = 0): array
    {
        $key = 'gif:search:'.md5(mb_strtolower(trim($query))).":{$limit}:{$offset}";

        return $this->cached($key, self::SEARCH_TTL_SECONDS, fn () => $this->normalize($this->request('search', [
            'q' => $query,
            'limit' => $limit,
            'offset' => $offset,
        ])));
    }

    private function cached(string $key, int $successTtl, \Closure $resolve): array
    {
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $result = $resolve();

        Cache::put($key, $result, $result === [] ? self::FAILURE_TTL_SECONDS : $successTtl);

        return $result;
    }

    private function request(string $endpoint, array $params): array
    {
        $baseUrl = rtrim((string) config('services.giphy.base_url'), '/');
        $apiKey = (string) config('services.giphy.key');

        $response = Http::timeout(5)->get("{$baseUrl}/{$endpoint}", array_merge($params, [
            'api_key' => $apiKey,
            'rating' => 'g',
        ]));

        if (!$response->successful()) {
            // Fail soft for the caller (the picker simply shows no results
            // rather than a hard error, since this is a non-critical
            // convenience feature) — but log loudly, because an empty list
            // looks identical to "no GIFs matched" otherwise. The most common
            // causes locally are GIPHY_API_KEY being unset, and in production,
            // 429 meaning the shared quota is exhausted.
            Log::warning('GifProviderService: upstream request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'rate_limited' => $response->status() === 429,
                'body' => $response->body(),
                'api_key_set' => $apiKey !== '',
            ]);

            return [];
        }

        return (array) ($response->json('data') ?? []);
    }

    private function normalize(array $items): array
    {
        return array_values(array_map(static function (array $item): array {
            $original = $item['images']['original'] ?? [];
            $preview = $item['images']['fixed_width_still']['url']
                ?? $item['images']['fixed_width']['url']
                ?? ($original['url'] ?? null);

            return [
                'id' => $item['id'] ?? null,
                'preview_url' => $preview,
                'url' => $original['url'] ?? null,
                'width' => isset($original['width']) ? (int) $original['width'] : null,
                'height' => isset($original['height']) ? (int) $original['height'] : null,
            ];
        }, $items));
    }
}
