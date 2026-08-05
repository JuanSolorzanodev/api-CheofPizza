<?php

declare(strict_types=1);

use App\Models\BankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

it(
    'returns null transfer configuration when no active bank account exists',
    function (): void {
        /** @var TestCase $this */
        config([
            'paypal.client_id' => '',
            'paypal.currency' => 'USD',
            'paypal.locale' => 'es-EC',
        ]);

        BankAccount::query()->create([
            'active' => false,
            'priority' => 1,
            'bank_name' => 'Banco Inactivo',
            'account_type' => 'Ahorros',
            'account_number' => '2200000001',
            'holder_name' => 'Cheo Pizza',
            'holder_id' => '1300000001',
            'qr_image_url' => null,
            'instructions' => null,
        ]);

        $this
            ->getJson('/api/v1/public/checkout/config')
            ->assertOk()
            ->assertJsonPath(
                'data.transfer',
                null,
            )
            ->assertJsonPath(
                'data.paypal.enabled',
                false,
            )
            ->assertJsonPath(
                'data.paypal.client_id',
                '',
            )
            ->assertJsonPath(
                'data.paypal.currency',
                'USD',
            )
            ->assertJsonPath(
                'data.paypal.locale',
                'es-EC',
            );
    },
);

it(
    'returns the active bank account with the highest priority',
    function (): void {
        /** @var TestCase $this */
        BankAccount::query()->create([
            'active' => true,
            'priority' => 3,
            'bank_name' => 'Banco Tercero',
            'account_type' => 'Corriente',
            'account_number' => '2200000003',
            'holder_name' => 'Cheo Pizza',
            'holder_id' => null,
            'qr_image_url' => null,
            'instructions' => null,
        ]);

        $primaryAccount = BankAccount::query()->create([
            'active' => true,
            'priority' => 1,
            'bank_name' => 'Banco Principal',
            'account_type' => 'Ahorros',
            'account_number' => '2200000001',
            'holder_name' => 'CHEO PIZZA S.A.',
            'holder_id' => '1300000001',
            'qr_image_url' => 'https://example.test/qr-primary.png',
            'instructions' => 'Enviar el comprobante después de transferir.',
        ]);

        BankAccount::query()->create([
            'active' => true,
            'priority' => 2,
            'bank_name' => 'Banco Secundario',
            'account_type' => 'Ahorros',
            'account_number' => '2200000002',
            'holder_name' => 'Cheo Pizza',
            'holder_id' => null,
            'qr_image_url' => null,
            'instructions' => null,
        ]);

        $this
            ->getJson('/api/v1/public/checkout/config')
            ->assertOk()
            ->assertJsonPath(
                'data.transfer.bank_name',
                $primaryAccount->bank_name,
            )
            ->assertJsonPath(
                'data.transfer.account_type',
                $primaryAccount->account_type,
            )
            ->assertJsonPath(
                'data.transfer.account_number',
                $primaryAccount->account_number,
            )
            ->assertJsonPath(
                'data.transfer.holder_name',
                $primaryAccount->holder_name,
            )
            ->assertJsonPath(
                'data.transfer.holder_id',
                $primaryAccount->holder_id,
            )
            ->assertJsonPath(
                'data.transfer.qr_image_url',
                $primaryAccount->qr_image_url,
            )
            ->assertJsonPath(
                'data.transfer.instructions',
                $primaryAccount->instructions,
            );
    },
);

it(
    'uses the oldest account when active accounts have the same priority',
    function (): void {
        /** @var TestCase $this */
        $firstAccount = BankAccount::query()->create([
            'active' => true,
            'priority' => 1,
            'bank_name' => 'Banco Primero',
            'account_type' => 'Ahorros',
            'account_number' => '2200000010',
            'holder_name' => 'Cheo Pizza',
            'holder_id' => null,
            'qr_image_url' => null,
            'instructions' => null,
        ]);

        BankAccount::query()->create([
            'active' => true,
            'priority' => 1,
            'bank_name' => 'Banco Segundo',
            'account_type' => 'Corriente',
            'account_number' => '2200000011',
            'holder_name' => 'Cheo Pizza',
            'holder_id' => null,
            'qr_image_url' => null,
            'instructions' => null,
        ]);

        $this
            ->getJson('/api/v1/public/checkout/config')
            ->assertOk()
            ->assertJsonPath(
                'data.transfer.bank_name',
                $firstAccount->bank_name,
            )
            ->assertJsonPath(
                'data.transfer.account_number',
                $firstAccount->account_number,
            );
    },
);

it(
    'ignores inactive bank accounts even when they have a higher priority',
    function (): void {
        /** @var TestCase $this */
        BankAccount::query()->create([
            'active' => false,
            'priority' => 1,
            'bank_name' => 'Banco Inactivo Prioritario',
            'account_type' => 'Ahorros',
            'account_number' => '2200000020',
            'holder_name' => 'Cheo Pizza',
            'holder_id' => null,
            'qr_image_url' => null,
            'instructions' => null,
        ]);

        $activeAccount = BankAccount::query()->create([
            'active' => true,
            'priority' => 5,
            'bank_name' => 'Banco Activo',
            'account_type' => 'Corriente',
            'account_number' => '2200000021',
            'holder_name' => 'Cheo Pizza',
            'holder_id' => null,
            'qr_image_url' => null,
            'instructions' => null,
        ]);

        $this
            ->getJson('/api/v1/public/checkout/config')
            ->assertOk()
            ->assertJsonPath(
                'data.transfer.bank_name',
                $activeAccount->bank_name,
            )
            ->assertJsonPath(
                'data.transfer.account_number',
                $activeAccount->account_number,
            );
    },
);

it(
    'returns the configured paypal public values',
    function (): void {
        /** @var TestCase $this */
        config([
            'paypal.client_id' => 'paypal-client-public-test',
            'paypal.currency' => 'EUR',
            'paypal.locale' => 'es-ES',
        ]);

        $this
            ->getJson('/api/v1/public/checkout/config')
            ->assertOk()
            ->assertJsonPath(
                'data.paypal.enabled',
                true,
            )
            ->assertJsonPath(
                'data.paypal.client_id',
                'paypal-client-public-test',
            )
            ->assertJsonPath(
                'data.paypal.currency',
                'EUR',
            )
            ->assertJsonPath(
                'data.paypal.locale',
                'es-ES',
            );
    },
);

it(
    'disables paypal when the client id is an empty string',
    function (): void {
        /** @var TestCase $this */
        config([
            'paypal.client_id' => '',
            'paypal.currency' => 'USD',
            'paypal.locale' => 'es-EC',
        ]);

        $this
            ->getJson('/api/v1/public/checkout/config')
            ->assertOk()
            ->assertJsonPath(
                'data.paypal.enabled',
                false,
            )
            ->assertJsonPath(
                'data.paypal.client_id',
                '',
            );
    },
);

it(
    'does not expose internal bank account fields',
    function (): void {
        /** @var TestCase $this */
        BankAccount::query()->create([
            'active' => true,
            'priority' => 1,
            'bank_name' => 'Banco Seguro',
            'account_type' => 'Ahorros',
            'account_number' => '2200000030',
            'holder_name' => 'Cheo Pizza',
            'holder_id' => '1300000030',
            'qr_image_url' => null,
            'instructions' => null,
        ]);

        $response = $this
            ->getJson('/api/v1/public/checkout/config')
            ->assertOk();

        $transfer = $response->json('data.transfer');

        expect($transfer)
            ->toBeArray()
            ->toHaveKeys([
                'bank_name',
                'account_type',
                'account_number',
                'holder_name',
                'holder_id',
                'qr_image_url',
                'instructions',
            ])
            ->not
            ->toHaveKeys([
                'id',
                'active',
                'priority',
                'created_at',
                'updated_at',
            ]);

        expect(array_keys($transfer))
            ->toBe([
                'bank_name',
                'account_type',
                'account_number',
                'holder_name',
                'holder_id',
                'qr_image_url',
                'instructions',
            ]);
    },
);

it(
    'returns nullable public bank account fields as null',
    function (): void {
        /** @var TestCase $this */
        BankAccount::query()->create([
            'active' => true,
            'priority' => 1,
            'bank_name' => 'Banco Sin Opcionales',
            'account_type' => 'Ahorros',
            'account_number' => '2200000040',
            'holder_name' => 'Cheo Pizza',
            'holder_id' => null,
            'qr_image_url' => null,
            'instructions' => null,
        ]);

        $this
            ->getJson('/api/v1/public/checkout/config')
            ->assertOk()
            ->assertJsonPath(
                'data.transfer.holder_id',
                null,
            )
            ->assertJsonPath(
                'data.transfer.qr_image_url',
                null,
            )
            ->assertJsonPath(
                'data.transfer.instructions',
                null,
            );
    },
);
