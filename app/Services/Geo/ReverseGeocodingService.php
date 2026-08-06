<?php

declare(strict_types=1);

namespace App\Services\Geo;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ReverseGeocodingService
{
    /**
     * @return array{
     *     formatted_address: string|null,
     *     place_id: string|null
     * }
     */
    public function reverse(
        float $latitude,
        float $longitude,
    ): array {
        $cacheKey = $this->cacheKey(
            latitude: $latitude,
            longitude: $longitude,
        );

        $cached = Cache::get(
            $cacheKey,
        );

        if (is_array($cached)) {
            return [
                'formatted_address' => isset($cached['formatted_address'])
                    ? (string) $cached['formatted_address']
                    : null,

                'place_id' => isset($cached['place_id'])
                    ? (string) $cached['place_id']
                    : null,
            ];
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'User-Agent' => 'CheofPizza/1.0 (reverse geocode)',
                    'Accept-Language' => 'es',
                ])
                ->connectTimeout(4)
                ->timeout(8)
                ->retry(
                    times: 3,
                    sleepMilliseconds: 250,
                    when: static fn (
                        Throwable $exception,
                    ): bool => $exception instanceof ConnectionException,
                    throw: false,
                )
                ->get(
                    'https://nominatim.openstreetmap.org/reverse',
                    [
                        'format' => 'jsonv2',
                        'lat' => $latitude,
                        'lon' => $longitude,
                        'zoom' => 18,
                        'addressdetails' => 1,
                    ],
                );

            if (
                ! $response instanceof Response
                || ! $response->successful()
            ) {
                return $this->storeFallback(
                    cacheKey: $cacheKey,
                );
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                return $this->storeFallback(
                    cacheKey: $cacheKey,
                );
            }

            $result = [
                'formatted_address' => isset($payload['display_name'])
                    ? (string) $payload['display_name']
                    : null,

                'place_id' => isset($payload['place_id'])
                    ? (string) $payload['place_id']
                    : null,
            ];

            Cache::put(
                $cacheKey,
                $result,
                now()->addDays(30),
            );

            return $result;
        } catch (Throwable) {
            return $this->storeFallback(
                cacheKey: $cacheKey,
            );
        }
    }

    private function cacheKey(
        float $latitude,
        float $longitude,
    ): string {
        $latitudeKey = number_format(
            $latitude,
            5,
            '.',
            '',
        );

        $longitudeKey = number_format(
            $longitude,
            5,
            '.',
            '',
        );

        return "geo:reverse:{$latitudeKey},{$longitudeKey}";
    }

    /**
     * @return array{
     *     formatted_address: null,
     *     place_id: null
     * }
     */
    private function storeFallback(
        string $cacheKey,
    ): array {
        $fallback = [
            'formatted_address' => null,
            'place_id' => null,
        ];

        Cache::put(
            $cacheKey,
            $fallback,
            now()->addMinutes(5),
        );

        return $fallback;
    }
}
