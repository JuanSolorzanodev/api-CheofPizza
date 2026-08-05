<?php

declare(strict_types=1);

use App\Models\BankAccount;
use App\Models\BusinessSetting;
use App\Models\WhatsAppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

it(
    'creates and returns default public business settings when none exist',
    function (): void {
        /** @var TestCase $this */
        config([
            'paypal.client_id' => 'paypal-client-test',
        ]);

        $this
            ->getJson('/api/v1/public/settings')
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Configuración pública recuperada correctamente.',
            )
            ->assertJsonPath(
                'data.business.name',
                "CHEO' PIZZA",
            )
            ->assertJsonPath(
                'data.business.phone',
                null,
            )
            ->assertJsonPath(
                'data.business.email',
                null,
            )
            ->assertJsonPath(
                'data.business.address',
                null,
            )
            ->assertJsonPath(
                'data.store.accepts_orders',
                true,
            )
            ->assertJsonPath(
                'data.store.closed_message',
                'En este momento la tienda no está recibiendo pedidos.',
            )
            ->assertJsonPath(
                'data.store.estimated_minutes',
                35,
            )
            ->assertJsonPath(
                'data.store.currency',
                'USD',
            )
            ->assertJsonPath(
                'data.store.timezone',
                'America/Guayaquil',
            )
            ->assertJsonPath(
                'data.delivery.pickup_enabled',
                true,
            )
            ->assertJsonPath(
                'data.delivery.delivery_enabled',
                true,
            )
            ->assertJsonPath(
                'data.delivery.delivery_fee',
                0,
            )
            ->assertJsonPath(
                'data.delivery.minimum_order',
                0,
            )
            ->assertJsonPath(
                'data.payments.paypal_enabled',
                true,
            )
            ->assertJsonPath(
                'data.payments.transfer_enabled',
                false,
            )
            ->assertJsonPath(
                'data.payments.cash_enabled',
                true,
            )
            ->assertJsonPath(
                'data.whatsapp.active',
                false,
            )
            ->assertJsonPath(
                'data.whatsapp.phone',
                null,
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
    'returns the configured public business information',
    function (): void {
        /** @var TestCase $this */
        config([
            'paypal.client_id' => 'paypal-client-test',
        ]);

        BusinessSetting::query()->create([
            'business_name' => "CHEO' PIZZA CALCETA",
            'phone' => '0999999999',
            'email' => 'ventas@cheopizza.test',
            'address' => 'Av. Principal y Calle 10',

            'accepts_orders' => false,
            'closed_message' => 'La tienda está cerrada por mantenimiento.',
            'estimated_minutes' => 50,
            'currency' => 'USD',
            'timezone' => 'America/Guayaquil',

            'pickup_enabled' => true,
            'delivery_enabled' => false,
            'delivery_fee' => '2.75',
            'minimum_order' => '8.50',

            'paypal_enabled' => true,
            'transfer_enabled' => true,
            'cash_enabled' => false,
        ]);

        WhatsAppSetting::query()->create([
            'active' => true,
            'phone' => '593999999999',
            'receipt_template' => 'Adjunto mi comprobante.',
        ]);

        BankAccount::query()->create([
            'active' => true,
            'priority' => 1,
            'bank_name' => 'Banco Pichincha',
            'account_type' => 'Ahorros',
            'account_number' => '2200000001',
            'holder_name' => 'Cheo Pizza',
            'holder_id' => '1300000001',
            'qr_image_url' => null,
            'instructions' => 'Enviar comprobante.',
        ]);

        $this
            ->getJson('/api/v1/public/settings')
            ->assertOk()
            ->assertJsonPath(
                'data.business.name',
                "CHEO' PIZZA CALCETA",
            )
            ->assertJsonPath(
                'data.business.phone',
                '0999999999',
            )
            ->assertJsonPath(
                'data.business.email',
                'ventas@cheopizza.test',
            )
            ->assertJsonPath(
                'data.business.address',
                'Av. Principal y Calle 10',
            )
            ->assertJsonPath(
                'data.store.accepts_orders',
                false,
            )
            ->assertJsonPath(
                'data.store.closed_message',
                'La tienda está cerrada por mantenimiento.',
            )
            ->assertJsonPath(
                'data.store.estimated_minutes',
                50,
            )
            ->assertJsonPath(
                'data.store.currency',
                'USD',
            )
            ->assertJsonPath(
                'data.store.timezone',
                'America/Guayaquil',
            )
            ->assertJsonPath(
                'data.delivery.pickup_enabled',
                true,
            )
            ->assertJsonPath(
                'data.delivery.delivery_enabled',
                false,
            )
            ->assertJsonPath(
                'data.delivery.delivery_fee',
                2.75,
            )
            ->assertJsonPath(
                'data.delivery.minimum_order',
                8.5,
            )
            ->assertJsonPath(
                'data.payments.paypal_enabled',
                true,
            )
            ->assertJsonPath(
                'data.payments.transfer_enabled',
                true,
            )
            ->assertJsonPath(
                'data.payments.cash_enabled',
                false,
            )
            ->assertJsonPath(
                'data.whatsapp.active',
                true,
            )
            ->assertJsonPath(
                'data.whatsapp.phone',
                '593999999999',
            );
    },
);

it(
    'disables paypal publicly when paypal is not configured',
    function (): void {
        /** @var TestCase $this */
        config([
            'paypal.client_id' => null,
        ]);

        BusinessSetting::query()->create([
            ...BusinessSetting::defaultValues(),
            'paypal_enabled' => true,
        ]);

        $this
            ->getJson('/api/v1/public/settings')
            ->assertOk()
            ->assertJsonPath(
                'data.payments.paypal_enabled',
                false,
            );
    },
);

it(
    'keeps paypal disabled when the business setting disables it',
    function (): void {
        /** @var TestCase $this */
        config([
            'paypal.client_id' => 'paypal-client-test',
        ]);

        BusinessSetting::query()->create([
            ...BusinessSetting::defaultValues(),
            'paypal_enabled' => false,
        ]);

        $this
            ->getJson('/api/v1/public/settings')
            ->assertOk()
            ->assertJsonPath(
                'data.payments.paypal_enabled',
                false,
            );
    },
);

it(
    'disables bank transfer when no active bank account exists',
    function (): void {
        /** @var TestCase $this */
        BusinessSetting::query()->create([
            ...BusinessSetting::defaultValues(),
            'transfer_enabled' => true,
        ]);

        BankAccount::query()->create([
            'active' => false,
            'priority' => 1,
            'bank_name' => 'Banco Inactivo',
            'account_type' => 'Ahorros',
            'account_number' => '2200000002',
            'holder_name' => 'Cheo Pizza',
            'holder_id' => null,
            'qr_image_url' => null,
            'instructions' => null,
        ]);

        $this
            ->getJson('/api/v1/public/settings')
            ->assertOk()
            ->assertJsonPath(
                'data.payments.transfer_enabled',
                false,
            );
    },
);

it(
    'enables bank transfer only when the setting and an active account exist',
    function (): void {
        /** @var TestCase $this */
        BusinessSetting::query()->create([
            ...BusinessSetting::defaultValues(),
            'transfer_enabled' => true,
        ]);

        BankAccount::query()->create([
            'active' => true,
            'priority' => 2,
            'bank_name' => 'Banco Secundario',
            'account_type' => 'Corriente',
            'account_number' => '2200000003',
            'holder_name' => 'Cheo Pizza',
            'holder_id' => null,
            'qr_image_url' => null,
            'instructions' => null,
        ]);

        BankAccount::query()->create([
            'active' => true,
            'priority' => 1,
            'bank_name' => 'Banco Principal',
            'account_type' => 'Ahorros',
            'account_number' => '2200000004',
            'holder_name' => 'Cheo Pizza',
            'holder_id' => null,
            'qr_image_url' => null,
            'instructions' => null,
        ]);

        $this
            ->getJson('/api/v1/public/settings')
            ->assertOk()
            ->assertJsonPath(
                'data.payments.transfer_enabled',
                true,
            );
    },
);

it(
    'keeps transfer disabled when the business setting disables it',
    function (): void {
        /** @var TestCase $this */
        BusinessSetting::query()->create([
            ...BusinessSetting::defaultValues(),
            'transfer_enabled' => false,
        ]);

        BankAccount::query()->create([
            'active' => true,
            'priority' => 1,
            'bank_name' => 'Banco Disponible',
            'account_type' => 'Ahorros',
            'account_number' => '2200000005',
            'holder_name' => 'Cheo Pizza',
            'holder_id' => null,
            'qr_image_url' => null,
            'instructions' => null,
        ]);

        $this
            ->getJson('/api/v1/public/settings')
            ->assertOk()
            ->assertJsonPath(
                'data.payments.transfer_enabled',
                false,
            );
    },
);

it(
    'hides the whatsapp phone when whatsapp is inactive',
    function (): void {
        /** @var TestCase $this */
        BusinessSetting::query()->create(
            BusinessSetting::defaultValues(),
        );

        WhatsAppSetting::query()->create([
            'active' => false,
            'phone' => '593988888888',
            'receipt_template' => 'Mensaje.',
        ]);

        $this
            ->getJson('/api/v1/public/settings')
            ->assertOk()
            ->assertJsonPath(
                'data.whatsapp.active',
                false,
            )
            ->assertJsonPath(
                'data.whatsapp.phone',
                null,
            );
    },
);
