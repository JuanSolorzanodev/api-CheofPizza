<?php

declare(strict_types=1);

use Tests\TestCase;

it(
    'returns the api identification response',
    function (): void {
        /** @var TestCase $this */
        $this
            ->getJson('/')
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'message',
                'CheofPizza API disponible.',
            )
            ->assertJsonPath(
                'data.service',
                'CheofPizza API',
            )
            ->assertJsonPath(
                'data.status',
                'up',
            )
            ->assertJsonPath(
                'data.version',
                'v1',
            )
            ->assertHeader(
                'X-Request-Id',
            );
    },
);

it(
    'returns the lightweight health response',
    function (): void {
        /** @var TestCase $this */
        $this
            ->getJson('/health')
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'message',
                'Servicio operativo.',
            )
            ->assertJsonPath(
                'data.status',
                'healthy',
            )
            ->assertJsonPath(
                'data.service',
                'CheofPizza API',
            )
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'status',
                    'service',
                    'timestamp',
                ],
            ])
            ->assertHeader(
                'X-Request-Id',
            );
    },
);
