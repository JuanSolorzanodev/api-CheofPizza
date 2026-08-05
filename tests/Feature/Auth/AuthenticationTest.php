<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Evita que los contadores del rate limiter de una prueba
         * afecten a las pruebas que se ejecutan posteriormente.
         */
        cache()->flush();
    }

    public function test_customer_can_register_and_receives_a_token(): void
    {
        $this->createRole('customer');

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'JUAN@EXAMPLE.COM',
            'phone' => '0987654321',
            'password' => 'Segura123',
            'password_confirmation' => 'Segura123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.user.email',
                'juan@example.com',
            )
            ->assertJsonPath(
                'data.user.role.name',
                'customer',
            )
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user',
                    'cart',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'juan@example.com',
            'phone' => '+593987654321',
            'is_active' => true,
        ]);

        $user = User::query()
            ->where(
                'email',
                'juan@example.com',
            )
            ->firstOrFail();

        $this->assertTrue(
            Hash::check(
                'Segura123',
                (string) $user->password,
            ),
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1,
        );
    }

    public function test_registration_never_accepts_a_role_from_the_client(): void
    {
        $customerRole = $this->createRole(
            'customer',
        );

        $this->createRole(
            'admin',
        );

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juan@example.com',
            'phone' => '0987654321',
            'password' => 'Segura123',
            'password_confirmation' => 'Segura123',
            'role_id' => 999,
            'role' => 'admin',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'juan@example.com',
            'role_id' => $customerRole->id,
        ]);
    }

    public function test_user_can_login_with_normalized_email(): void
    {
        $role = $this->createRole(
            'customer',
        );

        $user = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'cliente@example.com',
            'password' => 'Segura123',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => ' CLIENTE@EXAMPLE.COM ',
            'password' => 'Segura123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.user.id',
                $user->id,
            )
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user',
                    'cart',
                ],
            ]);

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1,
        );
    }

    public function test_login_uses_a_generic_message_for_invalid_credentials(): void
    {
        $this->createRole(
            'customer',
        );

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'Incorrecta123',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'code',
                'INVALID_CREDENTIALS',
            )
            ->assertJsonPath(
                'message',
                'El correo o la contraseña no son correctos.',
            );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0,
        );
    }

    public function test_inactive_user_cannot_login(): void
    {
        $role = $this->createRole(
            'customer',
        );

        User::factory()->create([
            'role_id' => $role->id,
            'email' => 'bloqueado@example.com',
            'password' => 'Segura123',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'bloqueado@example.com',
            'password' => 'Segura123',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'code',
                'USER_INACTIVE',
            );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0,
        );
    }

    public function test_authenticated_user_can_recover_their_session(): void
    {
        $role = $this->createRole(
            'customer',
        );

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $token = $user
            ->createToken('test-web')
            ->plainTextToken;

        $response = $this
            ->withToken($token)
            ->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.id',
                $user->id,
            )
            ->assertJsonPath(
                'data.role.name',
                'customer',
            );
    }

    public function test_unauthenticated_user_cannot_recover_a_session(): void
    {
        $this
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $role = $this->createRole(
            'customer',
        );

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $firstToken = $user
            ->createToken('first-device')
            ->plainTextToken;

        $user->createToken(
            'second-device',
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            2,
        );

        $this
            ->withToken($firstToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1,
        );
    }

    public function test_inactive_authenticated_user_has_current_token_revoked(): void
    {
        $role = $this->createRole(
            'customer',
        );

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => false,
        ]);

        $token = $user
            ->createToken('blocked-device')
            ->plainTextToken;

        $this
            ->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath(
                'code',
                'USER_INACTIVE',
            );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0,
        );
    }

    public function test_expired_token_cannot_access_authenticated_routes(): void
    {
        config()->set(
            'sanctum.expiration',
            60,
        );

        $role = $this->createRole(
            'customer',
        );

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $token = $user
            ->createToken('expired-device')
            ->plainTextToken;

        $user->tokens()
            ->latest('id')
            ->firstOrFail()
            ->forceFill([
                'created_at' => now()->subMinutes(61),
            ])
            ->save();

        $this
            ->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_login_is_rate_limited_after_too_many_attempts(): void
    {
        $this->createRole(
            'customer',
        );

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this
                ->postJson('/api/v1/auth/login', [
                    'email' => 'missing@example.com',
                    'password' => 'Incorrecta123',
                ])
                ->assertUnprocessable();
        }

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'missing@example.com',
                'password' => 'Incorrecta123',
            ])
            ->assertTooManyRequests()
            ->assertJsonPath(
                'code',
                'TOO_MANY_LOGIN_ATTEMPTS',
            );
    }

    public function test_registration_has_its_own_rate_limit_response(): void
    {
        $payload = [
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juan@example.com',
            'phone' => '0987654321',
            'password' => 'Segura123',
            'password_confirmation' => 'Segura123',
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $payload['email'] =
                "juan{$attempt}@example.com";

            $payload['phone'] =
                '09876543'.str_pad(
                    (string) $attempt,
                    2,
                    '0',
                    STR_PAD_LEFT,
                );

            $this
                ->postJson(
                    '/api/v1/auth/register',
                    $payload,
                )
                ->assertCreated();
        }

        $payload['email'] =
            'limit@example.com';

        $payload['phone'] =
            '0987654399';

        $this
            ->postJson(
                '/api/v1/auth/register',
                $payload,
            )
            ->assertTooManyRequests()
            ->assertJsonPath(
                'code',
                'TOO_MANY_REGISTRATION_ATTEMPTS',
            );
    }

    private function createRole(
        string $name,
    ): Role {
        return Role::query()->firstOrCreate([
            'role_name' => $name,
        ]);
    }
}
