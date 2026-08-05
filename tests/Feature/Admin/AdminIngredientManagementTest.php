<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientSizePrice;
use App\Models\IngredientType;
use App\Models\Pizza;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminIngredientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_ingredients(): void
    {
        $this
            ->getJson('/api/v1/admin/catalog/ingredients')
            ->assertUnauthorized();

        $this
            ->getJson('/api/v1/admin/catalog/ingredient-prices')
            ->assertUnauthorized();
    }

    public function test_operator_cannot_manage_ingredients_or_prices(): void
    {
        $operator = User::factory()
            ->operator()
            ->create();

        $type = IngredientType::query()->create([
            'type_name' => 'Carnes',
        ]);

        $ingredient = Ingredient::query()->create([
            'ingredient_type_id' => $type->id,
            'ingredient_name' => 'Jamón',
        ]);

        $size = Size::query()->create([
            'size_name' => 'Mediana',
            'portion' => 8,
        ]);

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->getJson('/api/v1/admin/catalog/ingredients')
            ->assertForbidden();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/ingredients',
                [
                    'ingredient_type_id' => $type->id,
                    'name' => 'Ingrediente no permitido',
                ],
            )
            ->assertForbidden();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->putJson(
                "/api/v1/admin/catalog/ingredients/{$ingredient->id}/prices",
                [
                    'prices' => [
                        [
                            'size_id' => $size->id,
                            'extra_price' => 1.50,
                        ],
                    ],
                ],
            )
            ->assertForbidden();

        $this->assertDatabaseMissing(
            'ingredients',
            [
                'ingredient_name' => 'Ingrediente no permitido',
            ],
        );

        $this->assertDatabaseCount(
            'ingredient_size_prices',
            0,
        );
    }

    public function test_admin_can_list_ingredients_with_type_prices_and_usage(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $type = IngredientType::query()->create([
            'type_name' => 'Quesos',
        ]);

        $ingredient = Ingredient::query()->create([
            'ingredient_type_id' => $type->id,
            'ingredient_name' => 'Mozzarella',
        ]);

        $size = Size::query()->create([
            'size_name' => 'Familiar',
            'portion' => 12,
        ]);

        IngredientSizePrice::query()->create([
            'ingredient_id' => $ingredient->id,
            'size_id' => $size->id,
            'extra_price' => '2.50',
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson('/api/v1/admin/catalog/ingredients');

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $ingredient->id,
            )
            ->assertJsonPath(
                'data.0.ingredient_type_id',
                (int) $type->id,
            )
            ->assertJsonPath(
                'data.0.name',
                'Mozzarella',
            )
            ->assertJsonPath(
                'data.0.type.id',
                (int) $type->id,
            )
            ->assertJsonPath(
                'data.0.type.name',
                'Quesos',
            )
            ->assertJsonPath(
                'data.0.prices.0.size.id',
                (int) $size->id,
            )
            ->assertJsonPath(
                'data.0.prices.0.extra_price',
                2.5,
            )
            ->assertJsonPath(
                'data.0.usage.pizzas',
                0,
            )
            ->assertJsonPath(
                'data.0.usage.prices',
                1,
            )
            ->assertJsonPath(
                'data.0.usage.total',
                0,
            )
            ->assertJsonPath(
                'data.0.can_delete',
                true,
            )
            ->assertJsonPath(
                'message',
                'Ingredientes recuperados correctamente.',
            );
    }

    public function test_admin_can_create_ingredient_with_normalized_name(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $type = IngredientType::query()->create([
            'type_name' => 'Vegetales',
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/ingredients',
                [
                    'ingredient_type_id' => $type->id,
                    'name' => '  Champiñones  ',
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.ingredient_type_id',
                (int) $type->id,
            )
            ->assertJsonPath(
                'data.name',
                'Champiñones',
            )
            ->assertJsonPath(
                'data.type.id',
                (int) $type->id,
            )
            ->assertJsonPath(
                'data.type.name',
                'Vegetales',
            )
            ->assertJsonPath(
                'data.prices',
                [],
            )
            ->assertJsonPath(
                'data.can_delete',
                true,
            )
            ->assertJsonPath(
                'message',
                'Ingrediente creado correctamente.',
            );

        $this->assertDatabaseHas(
            'ingredients',
            [
                'ingredient_type_id' => $type->id,
                'ingredient_name' => 'Champiñones',
            ],
        );
    }

    public function test_ingredient_name_must_be_unique_inside_the_same_type(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $firstType = IngredientType::query()->create([
            'type_name' => 'Carnes',
        ]);

        $secondType = IngredientType::query()->create([
            'type_name' => 'Vegetales',
        ]);

        Ingredient::query()->create([
            'ingredient_type_id' => $firstType->id,
            'ingredient_name' => 'Pepperoni',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/ingredients',
                [
                    'ingredient_type_id' => $firstType->id,
                    'name' => 'Pepperoni',
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ])
            ->assertJsonPath(
                'errors.name.0',
                'Ya existe un ingrediente con este nombre dentro del tipo seleccionado.',
            );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/ingredients',
                [
                    'ingredient_type_id' => $secondType->id,
                    'name' => 'Pepperoni',
                ],
            )
            ->assertCreated();

        expect(
            Ingredient::query()
                ->where(
                    'ingredient_name',
                    'Pepperoni',
                )
                ->count(),
        )->toBe(2);
    }

    public function test_ingredient_requires_an_existing_type_and_valid_name(): void
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
                '/api/v1/admin/catalog/ingredients',
                [
                    'ingredient_type_id' => 999999,
                    'name' => 'A',
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ingredient_type_id',
                'name',
            ]);

        $this->assertDatabaseCount(
            'ingredients',
            0,
        );
    }

    public function test_admin_can_view_and_update_ingredient(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $firstType = IngredientType::query()->create([
            'type_name' => 'Carnes',
        ]);

        $secondType = IngredientType::query()->create([
            'type_name' => 'Embutidos',
        ]);

        $ingredient = Ingredient::query()->create([
            'ingredient_type_id' => $firstType->id,
            'ingredient_name' => 'Jamón',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                "/api/v1/admin/catalog/ingredients/{$ingredient->id}",
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                (int) $ingredient->id,
            )
            ->assertJsonPath(
                'data.name',
                'Jamón',
            )
            ->assertJsonPath(
                'data.type.name',
                'Carnes',
            );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                "/api/v1/admin/catalog/ingredients/{$ingredient->id}",
                [
                    'ingredient_type_id' => $secondType->id,
                    'name' => '  Jamón premium  ',
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.ingredient_type_id',
                (int) $secondType->id,
            )
            ->assertJsonPath(
                'data.name',
                'Jamón premium',
            )
            ->assertJsonPath(
                'data.type.name',
                'Embutidos',
            )
            ->assertJsonPath(
                'message',
                'Ingrediente actualizado correctamente.',
            );

        $this->assertDatabaseHas(
            'ingredients',
            [
                'id' => $ingredient->id,
                'ingredient_type_id' => $secondType->id,
                'ingredient_name' => 'Jamón premium',
            ],
        );
    }

    public function test_updating_ingredient_can_keep_its_own_name(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $type = IngredientType::query()->create([
            'type_name' => 'Salsas',
        ]);

        $ingredient = Ingredient::query()->create([
            'ingredient_type_id' => $type->id,
            'ingredient_name' => 'Salsa BBQ',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                "/api/v1/admin/catalog/ingredients/{$ingredient->id}",
                [
                    'ingredient_type_id' => $type->id,
                    'name' => 'Salsa BBQ',
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Salsa BBQ',
            );

        $this->assertDatabaseHas(
            'ingredients',
            [
                'id' => $ingredient->id,
                'ingredient_type_id' => $type->id,
                'ingredient_name' => 'Salsa BBQ',
            ],
        );
    }

    public function test_admin_can_replace_all_prices_for_an_ingredient(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $type = IngredientType::query()->create([
            'type_name' => 'Extras',
        ]);

        $ingredient = Ingredient::query()->create([
            'ingredient_type_id' => $type->id,
            'ingredient_name' => 'Queso extra',
        ]);

        $small = Size::query()->create([
            'size_name' => 'Pequeña',
            'portion' => 4,
        ]);

        $medium = Size::query()->create([
            'size_name' => 'Mediana',
            'portion' => 8,
        ]);

        $large = Size::query()->create([
            'size_name' => 'Familiar',
            'portion' => 12,
        ]);

        IngredientSizePrice::query()->create([
            'ingredient_id' => $ingredient->id,
            'size_id' => $small->id,
            'extra_price' => '0.75',
        ]);

        IngredientSizePrice::query()->create([
            'ingredient_id' => $ingredient->id,
            'size_id' => $medium->id,
            'extra_price' => '1.50',
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                "/api/v1/admin/catalog/ingredients/{$ingredient->id}/prices",
                [
                    'prices' => [
                        [
                            'size_id' => $medium->id,
                            'extra_price' => 1.75,
                        ],
                        [
                            'size_id' => $large->id,
                            'extra_price' => 2.50,
                        ],
                    ],
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonCount(
                2,
                'data',
            )
            ->assertJsonPath(
                'data.0.size.id',
                (int) $medium->id,
            )
            ->assertJsonPath(
                'data.0.extra_price',
                1.75,
            )
            ->assertJsonPath(
                'data.1.size.id',
                (int) $large->id,
            )
            ->assertJsonPath(
                'data.1.extra_price',
                2.5,
            )
            ->assertJsonPath(
                'message',
                'Precios extra actualizados correctamente.',
            );

        $this->assertDatabaseMissing(
            'ingredient_size_prices',
            [
                'ingredient_id' => $ingredient->id,
                'size_id' => $small->id,
            ],
        );

        $this->assertDatabaseHas(
            'ingredient_size_prices',
            [
                'ingredient_id' => $ingredient->id,
                'size_id' => $medium->id,
                'extra_price' => '1.75',
            ],
        );

        $this->assertDatabaseHas(
            'ingredient_size_prices',
            [
                'ingredient_id' => $ingredient->id,
                'size_id' => $large->id,
                'extra_price' => '2.50',
            ],
        );

        $this->assertDatabaseCount(
            'ingredient_size_prices',
            2,
        );
    }

    public function test_admin_can_remove_all_prices_using_an_empty_array(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $type = IngredientType::query()->create([
            'type_name' => 'Extras',
        ]);

        $ingredient = Ingredient::query()->create([
            'ingredient_type_id' => $type->id,
            'ingredient_name' => 'Aceitunas',
        ]);

        $size = Size::query()->create([
            'size_name' => 'Grande',
            'portion' => 10,
        ]);

        IngredientSizePrice::query()->create([
            'ingredient_id' => $ingredient->id,
            'size_id' => $size->id,
            'extra_price' => '1.00',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                "/api/v1/admin/catalog/ingredients/{$ingredient->id}/prices",
                [
                    'prices' => [],
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data',
                [],
            );

        $this->assertDatabaseCount(
            'ingredient_size_prices',
            0,
        );
    }

    public function test_ingredient_prices_reject_duplicates_and_invalid_amounts(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $type = IngredientType::query()->create([
            'type_name' => 'Extras',
        ]);

        $ingredient = Ingredient::query()->create([
            'ingredient_type_id' => $type->id,
            'ingredient_name' => 'Tocino',
        ]);

        $size = Size::query()->create([
            'size_name' => 'Mediana',
            'portion' => 8,
        ]);

        IngredientSizePrice::query()->create([
            'ingredient_id' => $ingredient->id,
            'size_id' => $size->id,
            'extra_price' => '1.25',
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                "/api/v1/admin/catalog/ingredients/{$ingredient->id}/prices",
                [
                    'prices' => [
                        [
                            'size_id' => $size->id,
                            'extra_price' => -1,
                        ],
                        [
                            'size_id' => $size->id,
                            'extra_price' => 1.999,
                        ],
                    ],
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'prices.0.extra_price',
                'prices.1.size_id',
                'prices.1.extra_price',
            ]);

        $errors = $response->json('errors');

        expect(
            $errors['prices.0.extra_price'] ?? [],
        )->toContain(
            'El precio extra no puede ser negativo.',
        );

        expect(
            $errors['prices.1.size_id'] ?? [],
        )->toContain(
            'No puedes repetir tamaños.',
        );

        expect(
            $errors['prices.1.extra_price'] ?? [],
        )->toContain(
            'El precio extra puede tener como máximo 2 decimales.',
        );

        $this->assertDatabaseHas(
            'ingredient_size_prices',
            [
                'ingredient_id' => $ingredient->id,
                'size_id' => $size->id,
                'extra_price' => '1.25',
            ],
        );

        $this->assertDatabaseCount(
            'ingredient_size_prices',
            1,
        );
    }

    public function test_admin_can_list_all_ingredient_prices(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $type = IngredientType::query()->create([
            'type_name' => 'Extras',
        ]);

        $ingredient = Ingredient::query()->create([
            'ingredient_type_id' => $type->id,
            'ingredient_name' => 'Piña',
        ]);

        $size = Size::query()->create([
            'size_name' => 'Familiar',
            'portion' => 12,
        ]);

        $price = IngredientSizePrice::query()->create([
            'ingredient_id' => $ingredient->id,
            'size_id' => $size->id,
            'extra_price' => '1.80',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson('/api/v1/admin/catalog/ingredient-prices')
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                (int) $price->id,
            )
            ->assertJsonPath(
                'data.0.ingredient.id',
                (int) $ingredient->id,
            )
            ->assertJsonPath(
                'data.0.ingredient.name',
                'Piña',
            )
            ->assertJsonPath(
                'data.0.size.id',
                (int) $size->id,
            )
            ->assertJsonPath(
                'data.0.size.name',
                'Familiar',
            )
            ->assertJsonPath(
                'data.0.extra_price',
                1.8,
            )
            ->assertJsonPath(
                'message',
                'Precios extra recuperados correctamente.',
            );
    }

    public function test_admin_cannot_delete_ingredient_used_by_a_pizza(): void
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

        $category = Category::query()->create([
            'category_name' => 'Especiales',
            'description' => null,
        ]);

        $pizza = Pizza::query()->create([
            'category_id' => $category->id,
            'pizza_name' => 'Pizza Pepperoni',
            'description' => null,
            'image_url' => null,
            'is_visible' => true,
        ]);

        $pizza
            ->ingredients()
            ->attach(
                $ingredient->id,
            );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->deleteJson(
                "/api/v1/admin/catalog/ingredients/{$ingredient->id}",
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ingredient',
            ])
            ->assertJsonPath(
                'errors.ingredient.0',
                'No puedes eliminar este ingrediente porque forma parte de una o más pizzas.',
            );

        $this->assertDatabaseHas(
            'ingredients',
            [
                'id' => $ingredient->id,
            ],
        );

        $this->assertDatabaseHas(
            'pizza_ingredients',
            [
                'pizza_id' => $pizza->id,
                'ingredient_id' => $ingredient->id,
            ],
        );
    }

    public function test_deleting_unused_ingredient_also_deletes_its_prices(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $type = IngredientType::query()->create([
            'type_name' => 'Vegetales',
        ]);

        $ingredient = Ingredient::query()->create([
            'ingredient_type_id' => $type->id,
            'ingredient_name' => 'Cebolla',
        ]);

        $size = Size::query()->create([
            'size_name' => 'Grande',
            'portion' => 10,
        ]);

        $price = IngredientSizePrice::query()->create([
            'ingredient_id' => $ingredient->id,
            'size_id' => $size->id,
            'extra_price' => '0.80',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->deleteJson(
                "/api/v1/admin/catalog/ingredients/{$ingredient->id}",
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
                'Ingrediente eliminado correctamente.',
            );

        $this->assertDatabaseMissing(
            'ingredients',
            [
                'id' => $ingredient->id,
            ],
        );

        $this->assertDatabaseMissing(
            'ingredient_size_prices',
            [
                'id' => $price->id,
            ],
        );

        $this->assertDatabaseHas(
            'ingredient_types',
            [
                'id' => $type->id,
            ],
        );

        $this->assertDatabaseHas(
            'sizes',
            [
                'id' => $size->id,
            ],
        );
    }

    public function test_missing_ingredient_returns_not_found(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $type = IngredientType::query()->create([
            'type_name' => 'Extras',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/catalog/ingredients/999999',
            )
            ->assertNotFound();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                '/api/v1/admin/catalog/ingredients/999999',
                [
                    'ingredient_type_id' => $type->id,
                    'name' => 'Inexistente',
                ],
            )
            ->assertNotFound();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                '/api/v1/admin/catalog/ingredients/999999/prices',
                [
                    'prices' => [],
                ],
            )
            ->assertNotFound();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->deleteJson(
                '/api/v1/admin/catalog/ingredients/999999',
            )
            ->assertNotFound();
    }
}
