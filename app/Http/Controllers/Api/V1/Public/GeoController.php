<?php

namespace App\Http\Controllers\Api\V1\Public;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class GeoController
{
    /**
     * GET /api/v1/public/geo/reverse?lat=...&lng=...
     * Devuelve: { data: { formatted_address: string|null, place_id: string|null } }
     *
     * Buenas prácticas:
     * - Cache por coordenadas redondeadas.
     * - TTL éxito largo, TTL error corto.
     * - Timeouts y retry controlados.
     */
    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];

        $latKey = number_format($lat, 5, '.', '');
        $lngKey = number_format($lng, 5, '.', '');
        $cacheKey = "geo:reverse:{$latKey},{$lngKey}";

        $ttlSuccess = now()->addDays(30);
        $ttlFail    = now()->addMinutes(5);

        $fallback = [
            'formatted_address' => null,
            'place_id' => null,
        ];

        // Cache hit
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return response()->json(['data' => $cached]);
        }

        $client = new Client([
            'base_uri' => 'https://nominatim.openstreetmap.org/',
            'timeout' => 8.0,
            'connect_timeout' => 4.0,
            'headers' => [
                // Nominatim recomienda User-Agent identificable
                'User-Agent' => 'CheofPizza/1.0 (reverse geocode)',
                'Accept-Language' => 'es',
                'Accept' => 'application/json',
            ],
        ]);

        try {
            // Retry simple (2 intentos extra) con backoff corto
            $attempts = 0;
            $maxAttempts = 3;
            $lastException = null;

            while ($attempts < $maxAttempts) {
                $attempts++;

                try {
                    $response = $client->request('GET', 'reverse', [
                        'query' => [
                            'format' => 'jsonv2',
                            'lat' => $lat,
                            'lon' => $lng,
                            'zoom' => 18,
                            'addressdetails' => 1,
                        ],
                        // Evita que Guzzle lance excepción por 4xx/5xx automáticamente
                        'http_errors' => false,
                    ]);

                    $status = $response->getStatusCode();
                    if ($status < 200 || $status >= 300) {
                        Cache::put($cacheKey, $fallback, $ttlFail);
                        return response()->json(['data' => $fallback]);
                    }

                    $body = $response->getBody()->getContents();
                    $json = json_decode($body, true);

                    if (!is_array($json)) {
                        Cache::put($cacheKey, $fallback, $ttlFail);
                        return response()->json(['data' => $fallback]);
                    }

                    $data = [
                        'formatted_address' => isset($json['display_name']) ? (string) $json['display_name'] : null,
                        'place_id' => isset($json['place_id']) ? (string) $json['place_id'] : null,
                    ];

                    Cache::put($cacheKey, $data, $ttlSuccess);
                    return response()->json(['data' => $data]);
                } catch (GuzzleException $e) {
                    $lastException = $e;
                    // backoff ligero
                    usleep(250000); // 250ms
                }
            }

            // si fallaron todos los intentos
            Cache::put($cacheKey, $fallback, $ttlFail);
            return response()->json(['data' => $fallback]);

        } catch (Throwable $e) {
            Cache::put($cacheKey, $fallback, $ttlFail);
            return response()->json(['data' => $fallback]);
        }
    }
}
