<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $operatorRole;

    private Role $customerRole;

    protected function setUp(): void
    {
        parent::setUp();

        cache()->flush();

        $this->adminRole = $this->role('admin');
        $this->operatorRole = $this->role('operator');
        $this->customerRole = $this->role('customer');
    }

    public function test_unauthenticated_user_cannot_access_admin_users(): void
    {
        $this
            ->getJson('/api/v1/admin/users')
            ->assertUnauthorized();
    }

    public function test_customer_cannot_access_admin_users(): void
    {
        $customer = $this->userWithRole(
            $this->customerRole,
        );

        $this
            ->actingAs($customer)
            ->getJson('/api/v1/admin/users')
            ->assertForbidden()
            ->assertJsonPath(
                'code',
                'FORBIDDEN',
            );
    }

    public function test_operator_cannot_access_admin_users(): void
    {
        $operator = $this->userWithRole(
            $this->operatorRole,
        );

        $this
            ->actingAs($operator)
            ->getJson('/api/v1/admin/users')
            ->assertForbidden()
            ->assertJsonPath(
                'code',
                'FORBIDDEN',
            );
    }

    public function test_admin_can_list_users(): void
    {
        $admin = $this->userWithRole(
            $this->adminRole,
        );

        $this->userWithRole(
            $this->customerRole,
            [
                'first_name' => 'Cliente',
                'last_name' => 'Prueba',
                'email' => 'cliente@example.com',
            ],
        );

        $this
            ->actingAs($admin)
            ->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_admin_can_create_an_operator(): void
    {
        $admin = $this->userWithRole(
            $this->adminRole,
        );

        $response = $this
            ->actingAs($admin)
            ->postJson('/api/v1/admin/users', [
                'first_name' => '  María ',
                'last_name' => ' Operadora ',
                'phone' => '0987654321',
                'email' => ' OPERADORA@EXAMPLE.COM ',
                'role' => ' OPERATOR ',
                'is_active' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.email',
                'operadora@example.com',
            )
            ->assertJsonPath(
                'data.role.name',
                'operator',
            )
            ->assertJsonPath(
                'data.is_active',
                true,
            );

        $this->assertDatabaseHas('users', [
            'first_name' => 'María',
            'last_name' => 'Operadora',
            'email' => 'operadora@example.com',
            'role_id' => $this->operatorRole->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_change_their_own_role(): void
    {
        $admin = $this->userWithRole(
            $this->adminRole,
        );

        $this
            ->actingAs($admin)
            ->patchJson(
                "/api/v1/admin/users/{$admin->id}/role",
                [
                    'role' => 'operator',
                ],
            )
            ->assertConflict()
            ->assertJsonPath(
                'code',
                'CANNOT_CHANGE_OWN_ROLE',
            );

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role_id' => $this->adminRole->id,
        ]);
    }

    public function test_admin_cannot_disable_their_own_account(): void
    {
        $admin = $this->userWithRole(
            $this->adminRole,
        );

        $this
            ->actingAs($admin)
            ->patchJson(
                "/api/v1/admin/users/{$admin->id}/status",
                [
                    'is_active' => false,
                ],
            )
            ->assertConflict()
            ->assertJsonPath(
                'code',
                'CANNOT_DISABLE_OWN_ACCOUNT',
            );

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => true,
        ]);
    }

    public function test_changing_user_role_revokes_all_tokens(): void
    {
        $admin = $this->userWithRole(
            $this->adminRole,
        );

        $customer = $this->userWithRole(
            $this->customerRole,
        );

        $customer->createToken('device-one');
        $customer->createToken('device-two');

        $this->assertDatabaseCount(
            'personal_access_tokens',
            2,
        );

        $this
            ->actingAs($admin)
            ->patchJson(
                "/api/v1/admin/users/{$customer->id}/role",
                [
                    'role' => 'operator',
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.role.name',
                'operator',
            );

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'role_id' => $this->operatorRole->id,
        ]);

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0,
        );
    }

    public function test_disabling_user_revokes_all_tokens(): void
    {
        $admin = $this->userWithRole(
            $this->adminRole,
        );

        $customer = $this->userWithRole(
            $this->customerRole,
        );

        $customer->createToken('device-one');
        $customer->createToken('device-two');

        $this->assertDatabaseCount(
            'personal_access_tokens',
            2,
        );

        $this
            ->actingAs($admin)
            ->patchJson(
                "/api/v1/admin/users/{$customer->id}/status",
                [
                    'is_active' => false,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.is_active',
                false,
            );

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0,
        );
    }

    public function test_admin_can_update_user_information(): void
    {
        $admin = $this->userWithRole(
            $this->adminRole,
        );

        $customer = $this->userWithRole(
            $this->customerRole,
        );

        $this
            ->actingAs($admin)
            ->putJson(
                "/api/v1/admin/users/{$customer->id}",
                [
                    'first_name' => '  Carlos ',
                    'last_name' => ' Actualizado ',
                    'phone' => '0999999999',
                    'email' => ' CARLOS@EXAMPLE.COM ',
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.first_name',
                'Carlos',
            )
            ->assertJsonPath(
                'data.email',
                'carlos@example.com',
            );

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'first_name' => 'Carlos',
            'last_name' => 'Actualizado',
            'email' => 'carlos@example.com',
        ]);
    }

    public function test_admin_can_filter_users_by_role(): void
    {
        $admin = $this->userWithRole(
            $this->adminRole,
        );

        $operator = $this->userWithRole(
            $this->operatorRole,
            [
                'email' => 'operador@example.com',
            ],
        );

        $this->userWithRole(
            $this->customerRole,
            [
                'email' => 'cliente@example.com',
            ],
        );

        $response = $this
            ->actingAs($admin)
            ->getJson(
                '/api/v1/admin/users?role=operator',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'meta.total',
                1,
            )
            ->assertJsonPath(
                'data.0.id',
                $operator->id,
            )
            ->assertJsonPath(
                'data.0.role.name',
                'operator',
            );
    }

    public function test_admin_can_filter_users_by_status(): void
    {
        $admin = $this->userWithRole(
            $this->adminRole,
        );

        $inactiveCustomer = $this->userWithRole(
            $this->customerRole,
            [
                'email' => 'inactivo@example.com',
                'is_active' => false,
            ],
        );

        $this->userWithRole(
            $this->customerRole,
            [
                'email' => 'activo@example.com',
                'is_active' => true,
            ],
        );

        $response = $this
            ->actingAs($admin)
            ->getJson(
                '/api/v1/admin/users?status=inactive',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                1,
            )
            ->assertJsonPath(
                'data.0.id',
                $inactiveCustomer->id,
            )
            ->assertJsonPath(
                'data.0.is_active',
                false,
            );
    }

    public function test_admin_can_search_users_by_full_name(): void
    {
        $admin = $this->userWithRole(
            $this->adminRole,
        );

        $customer = $this->userWithRole(
            $this->customerRole,
            [
                'first_name' => 'María',
                'last_name' => 'Zambrano',
                'email' => 'maria@example.com',
            ],
        );

        $this->userWithRole(
            $this->customerRole,
            [
                'first_name' => 'Carlos',
                'last_name' => 'Mendoza',
                'email' => 'carlos@example.com',
            ],
        );

        $response = $this
            ->actingAs($admin)
            ->getJson(
                '/api/v1/admin/users?search=María%20Zambrano',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                1,
            )
            ->assertJsonPath(
                'data.0.id',
                $customer->id,
            );
    }

    public function test_admin_can_retrieve_administrable_roles(): void
    {
        $admin = $this->userWithRole(
            $this->adminRole,
        );

        $this
            ->actingAs($admin)
            ->getJson('/api/v1/admin/users/roles')
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.0.name',
                'admin',
            )
            ->assertJsonPath(
                'data.1.name',
                'operator',
            )
            ->assertJsonPath(
                'data.2.name',
                'customer',
            );
    }

    private function userWithRole(
        Role $role,
        array $attributes = [],
    ): User {
        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            ...$attributes,
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
