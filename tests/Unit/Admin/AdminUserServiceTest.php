<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\Admin\Users\AdminUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminUserServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminUserService $service;

    private Role $adminRole;

    private Role $operatorRole;

    private Role $customerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(
            AdminUserService::class,
        );

        $this->adminRole = $this->role(
            'admin',
        );

        $this->operatorRole = $this->role(
            'operator',
        );

        $this->customerRole = $this->role(
            'customer',
        );
    }

    public function test_it_creates_an_administrative_user(): void
    {
        $user = $this->service->create([
            'first_name' => 'María',
            'last_name' => 'Operadora',
            'phone' => '+593987654321',
            'email' => 'operadora@example.com',
            'role' => 'operator',
            'is_active' => true,
        ]);

        $this->assertNotNull(
            $user,
        );

        $this->assertSame(
            'operator',
            $user->role?->role_name,
        );

        $this->assertTrue(
            (bool) $user->is_active,
        );

        $this->assertDatabaseHas('users', [
            'email' => 'operadora@example.com',
            'role_id' => $this->operatorRole->id,
        ]);
    }

    public function test_it_returns_null_when_role_does_not_exist(): void
    {
        $user = $this->service->create([
            'first_name' => 'María',
            'last_name' => 'Operadora',
            'phone' => '+593987654321',
            'email' => 'operadora@example.com',
            'role' => 'missing-role',
            'is_active' => true,
        ]);

        $this->assertNull(
            $user,
        );

        $this->assertDatabaseMissing('users', [
            'email' => 'operadora@example.com',
        ]);
    }

    public function test_role_change_revokes_existing_tokens(): void
    {
        $user = $this->user(
            $this->customerRole,
        );

        $user->createToken('device-one');
        $user->createToken('device-two');

        $updatedUser = $this->service->changeRole(
            user: $user,
            roleName: 'operator',
        );

        $this->assertNotNull(
            $updatedUser,
        );

        $this->assertSame(
            'operator',
            $updatedUser->role?->role_name,
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0,
        );
    }

    public function test_disabling_user_revokes_existing_tokens(): void
    {
        $user = $this->user(
            $this->customerRole,
        );

        $user->createToken('device-one');
        $user->createToken('device-two');

        $updatedUser = $this->service->changeStatus(
            user: $user,
            isActive: false,
        );

        $this->assertFalse(
            (bool) $updatedUser->is_active,
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0,
        );
    }

    public function test_enabling_user_does_not_remove_tokens(): void
    {
        $user = $this->user(
            $this->customerRole,
            false,
        );

        $user->createToken('device-one');

        $updatedUser = $this->service->changeStatus(
            user: $user,
            isActive: true,
        );

        $this->assertTrue(
            (bool) $updatedUser->is_active,
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1,
        );
    }

    private function user(
        Role $role,
        bool $isActive = true,
    ): User {
        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => $isActive,
        ]);
    }

    private function role(
        string $name,
    ): Role {
        return Role::query()->firstOrCreate([
            'role_name' => $name,
        ]);
    }
}
