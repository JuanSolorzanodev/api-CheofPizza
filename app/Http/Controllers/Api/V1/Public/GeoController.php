<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class GeoController
{
    /**
     * Devuelve una dirección aproximada a partir de coordenadas.
     */
    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'lat' => [
                    'required',
                    'numeric',
                    'between:-90,90',
                ],

                'lng' => [
                    'required',
                    'numeric',
                    'between:-180,180',
                ],
            ],
            [
                'lat.required' => 'La latitud es obligatoria.',
                'lat.numeric' => 'La latitud debe ser numérica.',
                'lat.between' => 'La latitud debe estar entre -90 y 90.',

                'lng.required' => 'La longitud es obligatoria.',
                'lng.numeric' => 'La longitud debe ser numérica.',
                'lng.between' => 'La longitud debe estar entre -180 y 180.',
            ],
        );

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];

        $cacheKey = $this->cacheKey(
            lat: $lat,
            lng: $lng,
        );

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return response()->json([
                'data' => $cached,
            ]);
        }

        $fallback = $this->fallback();

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
                        'lat' => $lat,
                        'lon' => $lng,
                        'zoom' => 18,
                        'addressdetails' => 1,
                    ],
                );

            if (! $response instanceof Response || ! $response->successful()) {
                return $this->cacheFallback(
                    cacheKey: $cacheKey,
                    fallback: $fallback,
                );
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                return $this->cacheFallback(
                    cacheKey: $cacheKey,
                    fallback: $fallback,
                );
            }

            $data = [
                'formatted_address' => isset($payload['display_name'])
                    ? (string) $payload['display_name']
                    : null,

                'place_id' => isset($payload['place_id'])
                    ? (string) $payload['place_id']
                    : null,
            ];

            Cache::put(
                $cacheKey,
                $data,
                now()->addDays(30),
            );

            return response()->json([
                'data' => $data,
            ]);
        } catch (Throwable) {
            return $this->cacheFallback(
                cacheKey: $cacheKey,
                fallback: $fallback,
            );
        }
    }

    private function cacheKey(
        float $lat,
        float $lng,
    ): string {
        $latKey = number_format(
            $lat,
            5,
            '.',
            '',
        );

        $lngKey = number_format(
            $lng,
            5,
            '.',
            '',
        );

        return "geo:reverse:{$latKey},{$lngKey}";
    }

    /**
     * @return array{
     *     formatted_address: null,
     *     place_id: null
     * }
     */
    private function fallback(): array
    {
        return [
            'formatted_address' => null,
            'place_id' => null,
        ];
    }

    /**
     * @param array{
     *     formatted_address: null,
     *     place_id: null
     * } $fallback
     */
    private function cacheFallback(
        string $cacheKey,
        array $fallback,
    ): JsonResponse {
        Cache::put(
            $cacheKey,
            $fallback,
            now()->addMinutes(5),
        );

        return response()->json([
            'data' => $fallback,
        ]);
    }
}
