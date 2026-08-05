<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

it(
    'validates reverse geocoding coordinates',
    function (): void {
        /** @var TestCase $this */
        $this
            ->getJson('/api/v1/public/geo/reverse')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'lat',
                'lng',
            ])
            ->assertJsonPath(
                'errors.lat.0',
                'La latitud es obligatoria.',
            )
            ->assertJsonPath(
                'errors.lng.0',
                'La longitud es obligatoria.',
            );

        $this
            ->getJson(
                '/api/v1/public/geo/reverse?lat=91&lng=-181',
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'lat',
                'lng',
            ])
            ->assertJsonPath(
                'errors.lat.0',
                'La latitud debe estar entre -90 y 90.',
            )
            ->assertJsonPath(
                'errors.lng.0',
                'La longitud debe estar entre -180 y 180.',
            );
    },
);

it(
    'returns and caches a successful nominatim response',
    function (): void {
        /** @var TestCase $this */
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response(
                [
                    'display_name' => 'Calceta, Bolívar, Manabí, Ecuador',
                    'place_id' => 123456,
                ],
                200,
            ),
        ]);

        $this
            ->getJson(
                '/api/v1/public/geo/reverse?lat=-0.84561&lng=-80.16389',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.formatted_address',
                'Calceta, Bolívar, Manabí, Ecuador',
            )
            ->assertJsonPath(
                'data.place_id',
                '123456',
            );

        Http::assertSentCount(1);

        expect(
            Cache::get(
                'geo:reverse:-0.84561,-80.16389',
            ),
        )->toBe([
            'formatted_address' => 'Calceta, Bolívar, Manabí, Ecuador',
            'place_id' => '123456',
        ]);

        /*
         * La segunda consulta debe salir directamente de caché.
         */
        $this
            ->getJson(
                '/api/v1/public/geo/reverse?lat=-0.84561&lng=-80.16389',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.formatted_address',
                'Calceta, Bolívar, Manabí, Ecuador',
            )
            ->assertJsonPath(
                'data.place_id',
                '123456',
            );

        Http::assertSentCount(1);
    },
);

it(
    'uses coordinates rounded to five decimals as the cache key',
    function (): void {
        /** @var TestCase $this */
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response(
                [
                    'display_name' => 'Dirección redondeada',
                    'place_id' => 'rounded-place',
                ],
                200,
            ),
        ]);

        $this
            ->getJson(
                '/api/v1/public/geo/reverse?lat=-0.845611&lng=-80.163891',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.formatted_address',
                'Dirección redondeada',
            );

        /*
         * Estas coordenadas generan la misma clave redondeada.
         */
        $this
            ->getJson(
                '/api/v1/public/geo/reverse?lat=-0.845612&lng=-80.163892',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.formatted_address',
                'Dirección redondeada',
            );

        Http::assertSentCount(1);
    },
);

it(
    'returns and caches a safe fallback for unsuccessful responses',
    function (): void {
        /** @var TestCase $this */
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response(
                [
                    'message' => 'Service unavailable',
                ],
                503,
            ),
        ]);

        $this
            ->getJson(
                '/api/v1/public/geo/reverse?lat=-0.84561&lng=-80.16389',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.formatted_address',
                null,
            )
            ->assertJsonPath(
                'data.place_id',
                null,
            );

        Http::assertSentCount(1);

        expect(
            Cache::get(
                'geo:reverse:-0.84561,-80.16389',
            ),
        )->toBe([
            'formatted_address' => null,
            'place_id' => null,
        ]);

        $this
            ->getJson(
                '/api/v1/public/geo/reverse?lat=-0.84561&lng=-80.16389',
            )
            ->assertOk();

        Http::assertSentCount(1);
    },
);

it(
    'returns a safe fallback when nominatim returns invalid json',
    function (): void {
        /** @var TestCase $this */
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response(
                'not-json',
                200,
                [
                    'Content-Type' => 'text/plain',
                ],
            ),
        ]);

        $this
            ->getJson(
                '/api/v1/public/geo/reverse?lat=-0.84561&lng=-80.16389',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.formatted_address',
                null,
            )
            ->assertJsonPath(
                'data.place_id',
                null,
            );

        Http::assertSentCount(1);
    },
);

it(
    'sends the expected request to nominatim',
    function (): void {
        /** @var TestCase $this */
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response(
                [
                    'display_name' => 'Dirección de prueba',
                    'place_id' => 999,
                ],
                200,
            ),
        ]);

        $this
            ->getJson(
                '/api/v1/public/geo/reverse?lat=-0.84561&lng=-80.16389',
            )
            ->assertOk();

        Http::assertSent(
            static function ($request): bool {
                return str_starts_with(
                    $request->url(),
                    'https://nominatim.openstreetmap.org/reverse?',
                )
                    && $request['format'] === 'jsonv2'
                    && (float) $request['lat'] === -0.84561
                    && (float) $request['lon'] === -80.16389
                    && (int) $request['zoom'] === 18
                    && (int) $request['addressdetails'] === 1
                    && $request->hasHeader(
                        'User-Agent',
                        'CheofPizza/1.0 (reverse geocode)',
                    )
                    && $request->hasHeader(
                        'Accept-Language',
                        'es',
                    );
            },
        );

        Http::assertSentCount(1);
    },
);
