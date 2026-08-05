<?php

declare(strict_types=1);

use App\Models\BusinessSetting;
use App\Models\User;
use App\Models\WhatsAppSetting;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * @return array<string, mixed>
 */
function businessSettingsPayload(
    array $overrides = [],
): array {
    $payload = [
        'business' => [
            'name' => "CHEO' PIZZA",
            'phone' => '0999999999',
            'email' => 'contacto@cheofpizza.com',
            'address' => 'Ecuador',
        ],

        'store' => [
            'accepts_orders' => true,
            'closed_message' => 'En este momento no estamos recibiendo pedidos.',
            'estimated_minutes' => 35,
            'currency' => 'USD',
            'timezone' => 'America/Guayaquil',
        ],

        'delivery' => [
            'pickup_enabled' => true,
            'delivery_enabled' => true,
            'delivery_fee' => 1.50,
            'minimum_order' => 5.00,
        ],

        'payments' => [
            'paypal_enabled' => true,
            'transfer_enabled' => true,
            'cash_enabled' => true,
        ],

        'whatsapp' => [
            'active' => true,
            'phone' => '593999999999',
            'receipt_template' => 'Hola, adjunto el comprobante de mi pedido.',
        ],
    ];

    return array_replace_recursive(
        $payload,
        $overrides,
    );
}

describe('Configuración administrativa del negocio', function (): void {
    it(
        'requiere autenticación',
        function (): void {
            /** @var TestCase $this */
            $this
                ->getJson('/api/v1/admin/settings')
                ->assertUnauthorized();

            $this
                ->putJson(
                    '/api/v1/admin/settings',
                    businessSettingsPayload(),
                )
                ->assertUnauthorized();
        },
    );

    it(
        'impide el acceso a clientes',
        function (): void {
            /** @var TestCase $this */
            $customer = User::factory()
                ->customer()
                ->create();

            $this
                ->actingAs($customer, 'sanctum')
                ->getJson('/api/v1/admin/settings')
                ->assertForbidden();
        },
    );

    it(
        'permite al administrador consultar la configuración',
        function (): void {
            /** @var TestCase $this */
            $admin = User::factory()
                ->admin()
                ->create();

            $this
                ->actingAs($admin, 'sanctum')
                ->getJson('/api/v1/admin/settings')
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath(
                    'message',
                    'Configuración recuperada correctamente.',
                )
                ->assertJsonPath(
                    'data.business.name',
                    "CHEO' PIZZA",
                )
                ->assertJsonPath(
                    'data.store.currency',
                    'USD',
                )
                ->assertJsonPath(
                    'data.delivery.pickup_enabled',
                    true,
                )
                ->assertJsonPath(
                    'data.payments.cash_enabled',
                    true,
                );

            $this->assertDatabaseCount(
                'business_settings',
                1,
            );

            $this->assertDatabaseCount(
                'whats_app_settings',
                1,
            );
        },
    );

    it(
        'actualiza y persiste toda la configuración',
        function (): void {
            /** @var TestCase $this */
            $admin = User::factory()
                ->admin()
                ->create();

            $payload = businessSettingsPayload();

            $this
                ->actingAs($admin, 'sanctum')
                ->putJson(
                    '/api/v1/admin/settings',
                    $payload,
                )
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath(
                    'message',
                    'Configuración actualizada correctamente.',
                )
                ->assertJsonPath(
                    'data.business.phone',
                    '0999999999',
                )
                ->assertJsonPath(
                    'data.delivery.delivery_fee',
                    1.5,
                )
                ->assertJsonPath(
                    'data.delivery.minimum_order',
                    5,
                )
                ->assertJsonPath(
                    'data.whatsapp.active',
                    true,
                );

            $this->assertDatabaseHas(
                'business_settings',
                [
                    'business_name' => "CHEO' PIZZA",

                    'phone' => '0999999999',

                    'accepts_orders' => true,

                    'delivery_fee' => '1.50',

                    'minimum_order' => '5.00',

                    'paypal_enabled' => true,

                    'transfer_enabled' => true,

                    'cash_enabled' => true,
                ],
            );

            $this->assertDatabaseHas(
                'whats_app_settings',
                [
                    'active' => true,
                    'phone' => '593999999999',
                    'receipt_template' => 'Hola, adjunto el comprobante de mi pedido.',
                ],
            );

            expect(
                BusinessSetting::query()->count(),
            )->toBe(1);

            expect(
                WhatsAppSetting::query()->count(),
            )->toBe(1);
        },
    );

    it(
        'normaliza textos y correo antes de guardar',
        function (): void {
            /** @var TestCase $this */
            $admin = User::factory()
                ->admin()
                ->create();

            $payload = businessSettingsPayload([
                'business' => [
                    'name' => "  CHEO' PIZZA  ",
                    'phone' => '  0999999999  ',
                    'email' => '  CONTACTO@CHEOFPIZZA.COM  ',
                    'address' => '  Ecuador  ',
                ],
            ]);

            $this
                ->actingAs($admin, 'sanctum')
                ->putJson(
                    '/api/v1/admin/settings',
                    $payload,
                )
                ->assertOk()
                ->assertJsonPath(
                    'data.business.name',
                    "CHEO' PIZZA",
                )
                ->assertJsonPath(
                    'data.business.email',
                    'contacto@cheofpizza.com',
                );

            $this->assertDatabaseHas(
                'business_settings',
                [
                    'business_name' => "CHEO' PIZZA",

                    'phone' => '0999999999',

                    'email' => 'contacto@cheofpizza.com',

                    'address' => 'Ecuador',
                ],
            );
        },
    );

    it(
        'exige retiro o entrega a domicilio',
        function (): void {
            /** @var TestCase $this */
            $admin = User::factory()
                ->admin()
                ->create();

            $payload = businessSettingsPayload([
                'delivery' => [
                    'pickup_enabled' => false,
                    'delivery_enabled' => false,
                ],
            ]);

            $this
                ->actingAs($admin, 'sanctum')
                ->putJson(
                    '/api/v1/admin/settings',
                    $payload,
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'delivery',
                ]);
        },
    );

    it(
        'exige al menos un método de pago',
        function (): void {
            /** @var TestCase $this */
            $admin = User::factory()
                ->admin()
                ->create();

            $payload = businessSettingsPayload([
                'payments' => [
                    'paypal_enabled' => false,
                    'transfer_enabled' => false,
                    'cash_enabled' => false,
                ],
            ]);

            $this
                ->actingAs($admin, 'sanctum')
                ->putJson(
                    '/api/v1/admin/settings',
                    $payload,
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'payments',
                ]);
        },
    );

    it(
        'exige mensaje cuando la tienda está cerrada',
        function (): void {
            /** @var TestCase $this */
            $admin = User::factory()
                ->admin()
                ->create();

            $payload = businessSettingsPayload([
                'store' => [
                    'accepts_orders' => false,
                    'closed_message' => null,
                ],
            ]);

            $this
                ->actingAs($admin, 'sanctum')
                ->putJson(
                    '/api/v1/admin/settings',
                    $payload,
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'store.closed_message',
                ]);
        },
    );

    it(
        'expone públicamente solo la configuración segura',
        function (): void {
            /** @var TestCase $this */
            $admin = User::factory()
                ->admin()
                ->create();

            $this
                ->actingAs($admin, 'sanctum')
                ->putJson(
                    '/api/v1/admin/settings',
                    businessSettingsPayload(),
                )
                ->assertOk();

            Cache::clear();

            $this
                ->getJson('/api/v1/public/settings')
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath(
                    'data.business.name',
                    "CHEO' PIZZA",
                )
                ->assertJsonPath(
                    'data.delivery.delivery_fee',
                    1.5,
                )
                ->assertJsonPath(
                    'data.whatsapp.active',
                    true,
                )
                ->assertJsonMissingPath(
                    'data.whatsapp.receipt_template',
                )
                ->assertJsonMissingPath(
                    'data.payments.paypal_configured',
                );
        },
    );
});
