<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use App\Exceptions\Admin\LastActiveAdminException;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\Users\ActiveAdminGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ActiveAdminGuardTest extends TestCase
{
    use RefreshDatabase;

    private ActiveAdminGuard $guard;

    private Role $adminRole;

    private Role $operatorRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = app(
            ActiveAdminGuard::class,
        );

        $this->adminRole = $this->role(
            'admin',
        );

        $this->operatorRole = $this->role(
            'operator',
        );
    }

    public function test_last_active_admin_cannot_be_disabled(): void
    {
        $admin = $this->user(
            role: $this->adminRole,
            isActive: true,
        );

        $admin->load('role');

        $this->expectException(
            LastActiveAdminException::class,
        );

        $this->expectExceptionMessage(
            'No puedes bloquear al último administrador activo.',
        );

        $this->guard->ensureCanBeDisabled(
            user: $admin,
            newStatus: false,
        );
    }

    public function test_admin_can_lose_role_when_another_active_admin_exists(): void
    {
        $admin = $this->user(
            role: $this->adminRole,
            isActive: true,
        );

        $this->user(
            role: $this->adminRole,
            isActive: true,
        );

        $admin->load('role');

        $this->guard->ensureRoleCanBeChanged(
            user: $admin,
            newRole: $this->operatorRole,
        );

        $this->assertTrue(true);
    }

    public function test_admin_can_be_disabled_when_another_active_admin_exists(): void
    {
        $admin = $this->user(
            role: $this->adminRole,
            isActive: true,
        );

        $this->user(
            role: $this->adminRole,
            isActive: true,
        );

        $admin->load('role');

        $this->guard->ensureCanBeDisabled(
            user: $admin,
            newStatus: false,
        );

        $this->assertTrue(true);
    }

    public function test_inactive_admin_does_not_trigger_last_admin_rule(): void
    {
        $admin = $this->user(
            role: $this->adminRole,
            isActive: false,
        );

        $admin->load('role');

        $this->guard->ensureCanBeDisabled(
            user: $admin,
            newStatus: false,
        );

        $this->assertTrue(true);
    }

    public function test_operator_does_not_trigger_last_admin_rule(): void
    {
        $operator = $this->user(
            role: $this->operatorRole,
            isActive: true,
        );

        $operator->load('role');

        $this->guard->ensureCanBeDisabled(
            user: $operator,
            newStatus: false,
        );

        $this->guard->ensureRoleCanBeChanged(
            user: $operator,
            newRole: $this->adminRole,
        );

        $this->assertTrue(true);
    }

    public function test_changing_admin_to_same_admin_role_is_allowed(): void
    {
        $admin = $this->user(
            role: $this->adminRole,
            isActive: true,
        );

        $admin->load('role');

        $this->guard->ensureRoleCanBeChanged(
            user: $admin,
            newRole: $this->adminRole,
        );

        $this->assertTrue(true);
    }

    public function test_enabling_last_admin_is_allowed(): void
    {
        $admin = $this->user(
            role: $this->adminRole,
            isActive: false,
        );

        $admin->load('role');

        $this->guard->ensureCanBeDisabled(
            user: $admin,
            newStatus: true,
        );

        $this->assertTrue(true);
    }

    private function user(
        Role $role,
        bool $isActive,
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
