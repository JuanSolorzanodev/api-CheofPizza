<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Ingredient;
use App\Models\IngredientType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminIngredientTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_ingredient_types(): void
    {
        $this
            ->getJson('/api/v1/admin/catalog/ingredient-types')
            ->assertUnauthorized();
    }

    public function test_operator_cannot_manage_ingredient_types(): void
    {
        $operator = User::factory()
            ->operator()
            ->create();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/catalog/ingredient-types',
            )
            ->assertForbidden();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/ingredient-types',
                [
                    'name' => 'Tipo no permitido',
                ],
            )
            ->assertForbidden();

        $this->assertDatabaseMissing(
            'ingredient_types',
            [
                'type_name' => 'Tipo no permitido',
            ],
        );
    }

    public function test_admin_can_list_ingredient_types_with_counts(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $type = IngredientType::query()->create([
            'type_name' => 'Carnes',
        ]);

        Ingredient::query()->create([
            'ingredient_type_id' => $type->id,
            'ingredient_name' => 'Jamón',
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/catalog/ingredient-types',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $type->id,
            )
            ->assertJsonPath(
                'data.0.name',
                'Carnes',
            )
            ->assertJsonPath(
                'data.0.ingredients_count',
                1,
            )
            ->assertJsonPath(
                'data.0.can_delete',
                false,
            )
            ->assertJsonPath(
                'message',
                'Tipos de ingredientes recuperados correctamente.',
            );
    }

    public function test_admin_can_create_ingredient_type_with_normalized_name(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/ingredient-types',
                [
                    'name' => '  Vegetales  ',
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.name',
                'Vegetales',
            )
            ->assertJsonPath(
                'data.ingredients_count',
                0,
            )
            ->assertJsonPath(
                'data.can_delete',
                true,
            )
            ->assertJsonPath(
                'message',
                'Tipo de ingrediente creado correctamente.',
            );

        $this->assertDatabaseHas(
            'ingredient_types',
            [
                'type_name' => 'Vegetales',
            ],
        );
    }

    public function test_ingredient_type_name_must_be_unique(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        IngredientType::query()->create([
            'type_name' => 'Quesos',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/ingredient-types',
                [
                    'name' => 'Quesos',
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ])
            ->assertJsonPath(
                'errors.name.0',
                'Ya existe un tipo de ingrediente con este nombre.',
            );

        expect(
            IngredientType::query()
                ->where(
                    'type_name',
                    'Quesos',
                )
                ->count(),
        )->toBe(1);
    }

    public function test_ingredient_type_name_must_have_valid_length(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/ingredient-types',
                [
                    'name' => 'A',
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/ingredient-types',
                [
                    'name' => str_repeat(
                        'A',
                        101,
                    ),
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);

        $this->assertDatabaseCount(
            'ingredient_types',
            0,
        );
    }

    public function test_admin_can_view_and_update_ingredient_type(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $type = IngredientType::query()->create([
            'type_name' => 'Embutidos',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                "/api/v1/admin/catalog/ingredient-types/{$type->id}",
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                (int) $type->id,
            )
            ->assertJsonPath(
                'data.name',
                'Embutidos',
            )
            ->assertJsonPath(
                'data.ingredients_count',
                0,
            )
            ->assertJsonPath(
                'data.can_delete',
                true,
            );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                "/api/v1/admin/catalog/ingredient-types/{$type->id}",
                [
                    'name' => '  Proteínas  ',
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Proteínas',
            )
            ->assertJsonPath(
                'data.ingredients_count',
                0,
            )
            ->assertJsonPath(
                'data.can_delete',
                true,
            )
            ->assertJsonPath(
                'message',
                'Tipo de ingrediente actualizado correctamente.',
            );

        $this->assertDatabaseHas(
            'ingredient_types',
            [
                'id' => $type->id,
                'type_name' => 'Proteínas',
            ],
        );
    }

    public function test_updating_ingredient_type_can_keep_its_own_name(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $type = IngredientType::query()->create([
            'type_name' => 'Salsas',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                "/api/v1/admin/catalog/ingredient-types/{$type->id}",
                [
                    'name' => 'Salsas',
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Salsas',
            );

        $this->assertDatabaseHas(
            'ingredient_types',
            [
                'id' => $type->id,
                'type_name' => 'Salsas',
            ],
        );
    }

    public function test_admin_cannot_delete_ingredient_type_with_ingredients(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $type = IngredientType::query()->create([
            'type_name' => 'Carnes',
        ]);

        $ingredient = Ingredient::query()->create([
            'ingredient_type_id' => $type->id,
            'ingredient_name' => 'Pepperoni',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->deleteJson(
                "/api/v1/admin/catalog/ingredient-types/{$type->id}",
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ingredient_type',
            ])
            ->assertJsonPath(
                'errors.ingredient_type.0',
                'No puedes eliminar este tipo porque contiene ingredientes.',
            );

        $this->assertDatabaseHas(
            'ingredient_types',
            [
                'id' => $type->id,
            ],
        );

        $this->assertDatabaseHas(
            'ingredients',
            [
                'id' => $ingredient->id,
                'ingredient_type_id' => $type->id,
            ],
        );
    }

    public function test_admin_can_delete_unused_ingredient_type(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $type = IngredientType::query()->create([
            'type_name' => 'Tipo eliminable',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->deleteJson(
                "/api/v1/admin/catalog/ingredient-types/{$type->id}",
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data',
                null,
            )
            ->assertJsonPath(
                'message',
                'Tipo de ingrediente eliminado correctamente.',
            );

        $this->assertDatabaseMissing(
            'ingredient_types',
            [
                'id' => $type->id,
            ],
        );
    }

    public function test_missing_ingredient_type_returns_not_found(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/catalog/ingredient-types/999999',
            )
            ->assertNotFound();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                '/api/v1/admin/catalog/ingredient-types/999999',
                [
                    'name' => 'Inexistente',
                ],
            )
            ->assertNotFound();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->deleteJson(
                '/api/v1/admin/catalog/ingredient-types/999999',
            )
            ->assertNotFound();
    }
}
