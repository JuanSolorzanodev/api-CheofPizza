<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

describe('Autorización de pagos PayPal', function (): void {
    it(
        'rechaza la creación de una orden PayPal sin autenticación',
        function (): void {
            /** @var TestCase $this */

            $response = $this->postJson(
                '/api/v1/payments/paypal/orders',
                [
                    'delivery_type' => 'pickup',
                ],
                [
                    'Idempotency-Key' => fake()->uuid(),
                ],
            );

            $response->assertUnauthorized();
        },
    );

    it(
        'rechaza usuarios que no tienen el rol customer',
        function (): void {
            /** @var TestCase $this */

            $operator = User::factory()
                ->operator()
                ->create();

            Sanctum::actingAs($operator);

            $response = $this->postJson(
                '/api/v1/payments/paypal/orders',
                [
                    'delivery_type' => 'pickup',
                ],
                [
                    'Idempotency-Key' => fake()->uuid(),
                ],
            );

            $response->assertForbidden();
        },
    );
});
