<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\AuthManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

it(
    'allows an active authenticated user to access protected resources',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create([
                'is_active' => true,
            ]);

        $token = $customer
            ->createToken('active-user-test')
            ->plainTextToken;

        $this
            ->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                (int) $customer->id,
            );

        expect(
            $customer
                ->tokens()
                ->count(),
        )->toBe(1);
    },
);

it(
    'blocks an inactive authenticated user with the expected response',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create([
                'is_active' => false,
            ]);

        $token = $customer
            ->createToken('inactive-user-test')
            ->plainTextToken;

        $this
            ->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,

                'message' => 'Tu cuenta se encuentra bloqueada. Comunícate con el administrador.',

                'code' => 'USER_INACTIVE',
            ]);
    },
);

it(
    'revokes only the token used by an inactive user',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create([
                'is_active' => false,
            ]);

        $blockedToken = $customer
            ->createToken('blocked-current-token');

        $otherToken = $customer
            ->createToken('other-session-token');

        expect(
            $customer
                ->tokens()
                ->count(),
        )->toBe(2);

        $this
            ->withToken(
                $blockedToken->plainTextToken,
            )
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath(
                'code',
                'USER_INACTIVE',
            );

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            [
                'id' => $blockedToken
                    ->accessToken
                    ->id,
            ],
        );

        $this->assertDatabaseHas(
            'personal_access_tokens',
            [
                'id' => $otherToken
                    ->accessToken
                    ->id,
            ],
        );

        expect(
            $customer
                ->tokens()
                ->count(),
        )->toBe(1);
    },
);

it(
    'returns unauthenticated after the inactive token has been revoked',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create([
                'is_active' => false,
            ]);

        $token = $customer
            ->createToken('revoked-token-test')
            ->plainTextToken;

        $this
            ->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath(
                'code',
                'USER_INACTIVE',
            );

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            [
                'tokenable_id' => $customer->id,
                'name' => 'revoked-token-test',
            ],
        );

        /** @var AuthManager $authManager */
        $authManager = app('auth');
        $authManager->forgetGuards();

        $this
            ->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    },
);

it(
    'blocks inactive customers before accessing customer routes',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create([
                'is_active' => false,
            ]);

        $token = $customer
            ->createToken('inactive-customer-route')
            ->plainTextToken;

        $this
            ->withToken($token)
            ->getJson('/api/v1/my/orders')
            ->assertForbidden()
            ->assertJsonPath(
                'code',
                'USER_INACTIVE',
            )
            ->assertJsonPath(
                'message',
                'Tu cuenta se encuentra bloqueada. Comunícate con el administrador.',
            );
    },
);

it(
    'blocks inactive operators before accessing operator routes',
    function (): void {
        /** @var TestCase $this */
        $operator = User::factory()
            ->operator()
            ->create([
                'is_active' => false,
            ]);

        $token = $operator
            ->createToken('inactive-operator-route')
            ->plainTextToken;

        $this
            ->withToken($token)
            ->getJson('/api/v1/operator/orders')
            ->assertForbidden()
            ->assertJsonPath(
                'code',
                'USER_INACTIVE',
            );
    },
);

it(
    'blocks inactive administrators before accessing admin routes',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create([
                'is_active' => false,
            ]);

        $token = $admin
            ->createToken('inactive-admin-route')
            ->plainTextToken;

        $this
            ->withToken($token)
            ->getJson('/api/v1/admin/analytics/dashboard')
            ->assertForbidden()
            ->assertJsonPath(
                'code',
                'USER_INACTIVE',
            );
    },
);

it(
    'does not apply active user validation to public routes',
    function (): void {
        /** @var TestCase $this */
        $inactiveCustomer = User::factory()
            ->customer()
            ->create([
                'is_active' => false,
            ]);

        $token = $inactiveCustomer
            ->createToken('inactive-public-route')
            ->plainTextToken;

        $this
            ->withToken($token)
            ->getJson('/api/v1/public/catalog/categories')
            ->assertOk();

        /*
         * La ruta pública no debe revocar el token.
         */
        expect(
            $inactiveCustomer
                ->tokens()
                ->count(),
        )->toBe(1);
    },
);
