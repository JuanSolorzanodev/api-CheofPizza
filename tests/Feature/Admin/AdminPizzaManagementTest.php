<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartStatus;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientType;
use App\Models\Pizza;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminPizzaManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_pizzas(): void
    {
        $this
            ->getJson('/api/v1/admin/catalog/pizzas')
            ->assertUnauthorized();

        $this
            ->postJson(
                '/api/v1/admin/catalog/pizzas',
                [],
            )
            ->assertUnauthorized();
    }

    public function test_operator_cannot_manage_pizzas(): void
    {
        $operator = User::factory()
            ->operator()
            ->create();

        [
            'category' => $category,
            'ingredient' => $ingredient,
        ] = $this->pizzaCatalogFixture();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->getJson('/api/v1/admin/catalog/pizzas')
            ->assertForbidden();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/pizzas',
                [
                    'category_id' => $category->id,
                    'name' => 'Pizza no permitida',
                    'description' => null,
                    'image_url' => null,
                    'is_visible' => true,
                    'ingredient_ids' => [
                        $ingredient->id,
                    ],
                ],
            )
            ->assertForbidden();

        $this->assertDatabaseMissing(
            'pizzas',
            [
                'pizza_name' => 'Pizza no permitida',
            ],
        );
    }

    public function test_admin_can_list_pizzas_with_category_ingredients_and_usage(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $category,
            'ingredient_type' => $ingredientType,
            'ingredient' => $ingredient,
        ] = $this->pizzaCatalogFixture();

        $pizza = Pizza::query()->create([
            'category_id' => $category->id,
            'pizza_name' => 'Americana',
            'description' => 'Pizza americana.',
            'image_url' => 'https://example.com/americana.jpg',
            'is_visible' => true,
        ]);

        $pizza
            ->ingredients()
            ->attach(
                $ingredient->id,
            );

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson('/api/v1/admin/catalog/pizzas');

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonCount(
                1,
                'data',
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $pizza->id,
            )
            ->assertJsonPath(
                'data.0.category_id',
                (int) $category->id,
            )
            ->assertJsonPath(
                'data.0.name',
                'Americana',
            )
            ->assertJsonPath(
                'data.0.description',
                'Pizza americana.',
            )
            ->assertJsonPath(
                'data.0.image_url',
                'https://example.com/americana.jpg',
            )
            ->assertJsonPath(
                'data.0.is_visible',
                true,
            )
            ->assertJsonPath(
                'data.0.category.id',
                (int) $category->id,
            )
            ->assertJsonPath(
                'data.0.category.name',
                'Especiales',
            )
            ->assertJsonPath(
                'data.0.ingredients.0.id',
                (int) $ingredient->id,
            )
            ->assertJsonPath(
                'data.0.ingredients.0.name',
                'Jamón',
            )
            ->assertJsonPath(
                'data.0.ingredients.0.type.id',
                (int) $ingredientType->id,
            )
            ->assertJsonPath(
                'data.0.ingredients.0.type.name',
                'Carnes',
            )
            ->assertJsonPath(
                'data.0.ingredients_count',
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
                'Pizzas recuperadas correctamente.',
            );
    }

    public function test_admin_can_create_pizza_with_normalized_data_and_ingredients(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $category,
            'ingredient_type' => $ingredientType,
            'ingredient' => $firstIngredient,
        ] = $this->pizzaCatalogFixture();

        $secondIngredient = Ingredient::query()->create([
            'ingredient_type_id' => $ingredientType->id,
            'ingredient_name' => 'Queso',
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/pizzas',
                [
                    'category_id' => $category->id,
                    'name' => '  Pizza Suprema  ',
                    'description' => '  Pizza con ingredientes seleccionados.  ',
                    'image_url' => '  https://example.com/suprema.jpg  ',
                    'is_visible' => true,
                    'ingredient_ids' => [
                        $firstIngredient->id,
                        $secondIngredient->id,
                    ],
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.category_id',
                (int) $category->id,
            )
            ->assertJsonPath(
                'data.name',
                'Pizza Suprema',
            )
            ->assertJsonPath(
                'data.description',
                'Pizza con ingredientes seleccionados.',
            )
            ->assertJsonPath(
                'data.image_url',
                'https://example.com/suprema.jpg',
            )
            ->assertJsonPath(
                'data.is_visible',
                true,
            )
            ->assertJsonPath(
                'data.ingredients_count',
                2,
            )
            ->assertJsonPath(
                'data.can_delete',
                true,
            )
            ->assertJsonPath(
                'message',
                'Pizza creada correctamente.',
            );

        $pizza = Pizza::query()
            ->where(
                'pizza_name',
                'Pizza Suprema',
            )
            ->firstOrFail();

        $this->assertDatabaseHas(
            'pizzas',
            [
                'id' => $pizza->id,
                'category_id' => $category->id,
                'pizza_name' => 'Pizza Suprema',
                'description' => 'Pizza con ingredientes seleccionados.',
                'image_url' => 'https://example.com/suprema.jpg',
                'is_visible' => true,
            ],
        );

        $this->assertDatabaseHas(
            'pizza_ingredients',
            [
                'pizza_id' => $pizza->id,
                'ingredient_id' => $firstIngredient->id,
            ],
        );

        $this->assertDatabaseHas(
            'pizza_ingredients',
            [
                'pizza_id' => $pizza->id,
                'ingredient_id' => $secondIngredient->id,
            ],
        );

        $this->assertDatabaseCount(
            'pizza_ingredients',
            2,
        );
    }

    public function test_pizza_name_must_be_unique_inside_the_same_category(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $firstCategory,
            'ingredient' => $ingredient,
        ] = $this->pizzaCatalogFixture();

        $secondCategory = Category::query()->create([
            'category_name' => 'Tradicionales',
            'description' => null,
        ]);

        Pizza::query()->create([
            'category_id' => $firstCategory->id,
            'pizza_name' => 'Pepperoni',
            'description' => null,
            'image_url' => null,
            'is_visible' => true,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/pizzas',
                [
                    'category_id' => $firstCategory->id,
                    'name' => 'Pepperoni',
                    'description' => null,
                    'image_url' => null,
                    'is_visible' => true,
                    'ingredient_ids' => [
                        $ingredient->id,
                    ],
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ])
            ->assertJsonPath(
                'errors.name.0',
                'Ya existe una pizza con este nombre dentro de la categoría seleccionada.',
            );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/pizzas',
                [
                    'category_id' => $secondCategory->id,
                    'name' => 'Pepperoni',
                    'description' => null,
                    'image_url' => null,
                    'is_visible' => true,
                    'ingredient_ids' => [
                        $ingredient->id,
                    ],
                ],
            )
            ->assertCreated();

        expect(
            Pizza::query()
                ->where(
                    'pizza_name',
                    'Pepperoni',
                )
                ->count(),
        )->toBe(2);
    }

    public function test_pizza_requires_valid_category_ingredients_and_image_url(): void
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
                '/api/v1/admin/catalog/pizzas',
                [
                    'category_id' => 999999,
                    'name' => 'A',
                    'description' => str_repeat(
                        'D',
                        3001,
                    ),
                    'image_url' => 'imagen-no-valida',
                    'is_visible' => true,
                    'ingredient_ids' => [
                        999999,
                    ],
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category_id',
                'name',
                'description',
                'image_url',
                'ingredient_ids.0',
            ]);

        $this->assertDatabaseCount(
            'pizzas',
            0,
        );

        $this->assertDatabaseCount(
            'pizza_ingredients',
            0,
        );
    }

    public function test_pizza_requires_at_least_one_non_repeated_ingredient(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $category,
            'ingredient' => $ingredient,
        ] = $this->pizzaCatalogFixture();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/pizzas',
                [
                    'category_id' => $category->id,
                    'name' => 'Sin ingredientes',
                    'description' => null,
                    'image_url' => null,
                    'is_visible' => true,
                    'ingredient_ids' => [],
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ingredient_ids',
            ])
            ->assertJsonPath(
                'errors.ingredient_ids.0',
                'Selecciona al menos un ingrediente.',
            );

        $duplicateResponse = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/pizzas',
                [
                    'category_id' => $category->id,
                    'name' => 'Ingredientes repetidos',
                    'description' => null,
                    'image_url' => null,
                    'is_visible' => true,
                    'ingredient_ids' => [
                        $ingredient->id,
                        $ingredient->id,
                    ],
                ],
            );

        $duplicateResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ingredient_ids.0',
                'ingredient_ids.1',
            ]);

        $errors = $duplicateResponse->json(
            'errors',
        );

        expect(
            $errors['ingredient_ids.0'] ?? [],
        )->toContain(
            'No puedes repetir ingredientes.',
        );

        expect(
            $errors['ingredient_ids.1'] ?? [],
        )->toContain(
            'No puedes repetir ingredientes.',
        );

        $this->assertDatabaseCount(
            'pizzas',
            0,
        );
    }

    public function test_admin_can_view_and_update_pizza_replacing_ingredients(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $firstCategory,
            'ingredient_type' => $ingredientType,
            'ingredient' => $firstIngredient,
        ] = $this->pizzaCatalogFixture();

        $secondCategory = Category::query()->create([
            'category_name' => 'Premium',
            'description' => null,
        ]);

        $secondIngredient = Ingredient::query()->create([
            'ingredient_type_id' => $ingredientType->id,
            'ingredient_name' => 'Pepperoni',
        ]);

        $thirdIngredient = Ingredient::query()->create([
            'ingredient_type_id' => $ingredientType->id,
            'ingredient_name' => 'Tocino',
        ]);

        $pizza = Pizza::query()->create([
            'category_id' => $firstCategory->id,
            'pizza_name' => 'Pizza inicial',
            'description' => 'Descripción inicial.',
            'image_url' => 'https://example.com/inicial.jpg',
            'is_visible' => true,
        ]);

        $pizza
            ->ingredients()
            ->attach([
                $firstIngredient->id,
                $secondIngredient->id,
            ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                "/api/v1/admin/catalog/pizzas/{$pizza->id}",
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                (int) $pizza->id,
            )
            ->assertJsonPath(
                'data.name',
                'Pizza inicial',
            )
            ->assertJsonPath(
                'data.ingredients_count',
                2,
            );

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                "/api/v1/admin/catalog/pizzas/{$pizza->id}",
                [
                    'category_id' => $secondCategory->id,
                    'name' => '  Pizza actualizada  ',
                    'description' => '  Nueva descripción.  ',
                    'image_url' => '  https://example.com/actualizada.jpg  ',
                    'is_visible' => false,
                    'ingredient_ids' => [
                        $secondIngredient->id,
                        $thirdIngredient->id,
                    ],
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.category_id',
                (int) $secondCategory->id,
            )
            ->assertJsonPath(
                'data.name',
                'Pizza actualizada',
            )
            ->assertJsonPath(
                'data.description',
                'Nueva descripción.',
            )
            ->assertJsonPath(
                'data.image_url',
                'https://example.com/actualizada.jpg',
            )
            ->assertJsonPath(
                'data.is_visible',
                false,
            )
            ->assertJsonPath(
                'data.ingredients_count',
                2,
            )
            ->assertJsonPath(
                'message',
                'Pizza actualizada correctamente.',
            );

        $this->assertDatabaseHas(
            'pizzas',
            [
                'id' => $pizza->id,
                'category_id' => $secondCategory->id,
                'pizza_name' => 'Pizza actualizada',
                'description' => 'Nueva descripción.',
                'image_url' => 'https://example.com/actualizada.jpg',
                'is_visible' => false,
            ],
        );

        $this->assertDatabaseMissing(
            'pizza_ingredients',
            [
                'pizza_id' => $pizza->id,
                'ingredient_id' => $firstIngredient->id,
            ],
        );

        $this->assertDatabaseHas(
            'pizza_ingredients',
            [
                'pizza_id' => $pizza->id,
                'ingredient_id' => $secondIngredient->id,
            ],
        );

        $this->assertDatabaseHas(
            'pizza_ingredients',
            [
                'pizza_id' => $pizza->id,
                'ingredient_id' => $thirdIngredient->id,
            ],
        );

        expect(
            $pizza
                ->fresh()
                ?->ingredients()
                ->count(),
        )->toBe(2);
    }

    public function test_updating_pizza_can_keep_its_own_name(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $category,
            'ingredient' => $ingredient,
        ] = $this->pizzaCatalogFixture();

        $pizza = Pizza::query()->create([
            'category_id' => $category->id,
            'pizza_name' => 'Hawaiana',
            'description' => 'Descripción inicial.',
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
            ->putJson(
                "/api/v1/admin/catalog/pizzas/{$pizza->id}",
                [
                    'category_id' => $category->id,
                    'name' => 'Hawaiana',
                    'description' => 'Descripción modificada.',
                    'image_url' => null,
                    'is_visible' => true,
                    'ingredient_ids' => [
                        $ingredient->id,
                    ],
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Hawaiana',
            )
            ->assertJsonPath(
                'data.description',
                'Descripción modificada.',
            );

        $this->assertDatabaseHas(
            'pizzas',
            [
                'id' => $pizza->id,
                'pizza_name' => 'Hawaiana',
                'description' => 'Descripción modificada.',
            ],
        );
    }

    public function test_invalid_update_does_not_modify_pizza_or_ingredient_relations(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $category,
            'ingredient_type' => $ingredientType,
            'ingredient' => $firstIngredient,
        ] = $this->pizzaCatalogFixture();

        $secondIngredient = Ingredient::query()->create([
            'ingredient_type_id' => $ingredientType->id,
            'ingredient_name' => 'Queso',
        ]);

        $existingPizza = Pizza::query()->create([
            'category_id' => $category->id,
            'pizza_name' => 'Nombre ocupado',
            'description' => null,
            'image_url' => null,
            'is_visible' => true,
        ]);

        $pizza = Pizza::query()->create([
            'category_id' => $category->id,
            'pizza_name' => 'Pizza original',
            'description' => 'Descripción original.',
            'image_url' => 'https://example.com/original.jpg',
            'is_visible' => true,
        ]);

        $pizza
            ->ingredients()
            ->attach(
                $firstIngredient->id,
            );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                "/api/v1/admin/catalog/pizzas/{$pizza->id}",
                [
                    'category_id' => $category->id,
                    'name' => $existingPizza->pizza_name,
                    'description' => 'No debe guardarse.',
                    'image_url' => 'https://example.com/no-guardar.jpg',
                    'is_visible' => false,
                    'ingredient_ids' => [
                        $secondIngredient->id,
                    ],
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);

        $this->assertDatabaseHas(
            'pizzas',
            [
                'id' => $pizza->id,
                'category_id' => $category->id,
                'pizza_name' => 'Pizza original',
                'description' => 'Descripción original.',
                'image_url' => 'https://example.com/original.jpg',
                'is_visible' => true,
            ],
        );

        $this->assertDatabaseHas(
            'pizza_ingredients',
            [
                'pizza_id' => $pizza->id,
                'ingredient_id' => $firstIngredient->id,
            ],
        );

        $this->assertDatabaseMissing(
            'pizza_ingredients',
            [
                'pizza_id' => $pizza->id,
                'ingredient_id' => $secondIngredient->id,
            ],
        );

        expect(
            $pizza
                ->fresh()
                ?->ingredients()
                ->count(),
        )->toBe(1);
    }

    public function test_admin_can_change_pizza_visibility(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $category,
            'ingredient' => $ingredient,
        ] = $this->pizzaCatalogFixture();

        $pizza = Pizza::query()->create([
            'category_id' => $category->id,
            'pizza_name' => 'Pizza visible',
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
            ->patchJson(
                "/api/v1/admin/catalog/pizzas/{$pizza->id}/visibility",
                [
                    'is_visible' => false,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.is_visible',
                false,
            )
            ->assertJsonPath(
                'message',
                'Pizza ocultada del catálogo.',
            );

        $this->assertDatabaseHas(
            'pizzas',
            [
                'id' => $pizza->id,
                'is_visible' => false,
            ],
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->patchJson(
                "/api/v1/admin/catalog/pizzas/{$pizza->id}/visibility",
                [
                    'is_visible' => true,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.is_visible',
                true,
            )
            ->assertJsonPath(
                'message',
                'Pizza visible en el catálogo.',
            );

        $this->assertDatabaseHas(
            'pizzas',
            [
                'id' => $pizza->id,
                'is_visible' => true,
            ],
        );
    }

    public function test_admin_cannot_delete_pizza_used_in_an_active_cart(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        [
            'category' => $category,
            'ingredient' => $ingredient,
        ] = $this->pizzaCatalogFixture();

        $pizza = Pizza::query()->create([
            'category_id' => $category->id,
            'pizza_name' => 'Pizza en carrito',
            'description' => null,
            'image_url' => null,
            'is_visible' => true,
        ]);

        $pizza
            ->ingredients()
            ->attach(
                $ingredient->id,
            );

        $size = Size::query()->create([
            'size_name' => 'Mediana',
            'portion' => 8,
        ]);

        $activeStatus = CartStatus::query()
            ->firstOrCreate([
                'status_name' => 'active',
            ]);

        $cart = Cart::query()->create([
            'user_id' => $customer->id,
            'cart_status_id' => $activeStatus->id,
            'session_id' => null,
            'total' => '12.00',
        ]);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'item_type' => 'pizza',
            'pizza_id' => $pizza->id,
            'pizza_id_second' => null,
            'promotion_id' => null,
            'size_id' => $size->id,
            'is_half_and_half' => false,
            'quantity' => 1,
            'unit_price' => '12.00',
            'subtotal' => '12.00',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->deleteJson(
                "/api/v1/admin/catalog/pizzas/{$pizza->id}",
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'pizza',
            ])
            ->assertJsonPath(
                'errors.pizza.0',
                'No puedes eliminar esta pizza porque está siendo utilizada en carritos activos. Ocúltala temporalmente.',
            );

        $this->assertDatabaseHas(
            'pizzas',
            [
                'id' => $pizza->id,
            ],
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'id' => $cart->cartItems()
                    ->firstOrFail()
                    ->id,
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

    public function test_admin_can_delete_unused_pizza_and_detach_ingredients(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $category,
            'ingredient_type' => $ingredientType,
            'ingredient' => $firstIngredient,
        ] = $this->pizzaCatalogFixture();

        $secondIngredient = Ingredient::query()->create([
            'ingredient_type_id' => $ingredientType->id,
            'ingredient_name' => 'Queso',
        ]);

        $pizza = Pizza::query()->create([
            'category_id' => $category->id,
            'pizza_name' => 'Pizza eliminable',
            'description' => null,
            'image_url' => 'https://example.com/eliminable.jpg',
            'is_visible' => false,
        ]);

        $pizza
            ->ingredients()
            ->attach([
                $firstIngredient->id,
                $secondIngredient->id,
            ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->deleteJson(
                "/api/v1/admin/catalog/pizzas/{$pizza->id}",
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
                'Pizza eliminada correctamente.',
            );

        $this->assertDatabaseMissing(
            'pizzas',
            [
                'id' => $pizza->id,
            ],
        );

        $this->assertDatabaseMissing(
            'pizza_ingredients',
            [
                'pizza_id' => $pizza->id,
            ],
        );

        $this->assertDatabaseHas(
            'categories',
            [
                'id' => $category->id,
            ],
        );

        $this->assertDatabaseHas(
            'ingredients',
            [
                'id' => $firstIngredient->id,
            ],
        );

        $this->assertDatabaseHas(
            'ingredients',
            [
                'id' => $secondIngredient->id,
            ],
        );
    }

    public function test_missing_pizza_returns_not_found(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $category,
            'ingredient' => $ingredient,
        ] = $this->pizzaCatalogFixture();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/catalog/pizzas/999999',
            )
            ->assertNotFound();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                '/api/v1/admin/catalog/pizzas/999999',
                [
                    'category_id' => $category->id,
                    'name' => 'Pizza inexistente',
                    'description' => null,
                    'image_url' => null,
                    'is_visible' => true,
                    'ingredient_ids' => [
                        $ingredient->id,
                    ],
                ],
            )
            ->assertNotFound();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->patchJson(
                '/api/v1/admin/catalog/pizzas/999999/visibility',
                [
                    'is_visible' => false,
                ],
            )
            ->assertNotFound();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->deleteJson(
                '/api/v1/admin/catalog/pizzas/999999',
            )
            ->assertNotFound();
    }

    /**
     * @return array{
     *     category: Category,
     *     ingredient_type: IngredientType,
     *     ingredient: Ingredient
     * }
     */
    private function pizzaCatalogFixture(): array
    {
        $category = Category::query()->create([
            'category_name' => 'Especiales',
            'description' => 'Categoría de prueba.',
        ]);

        $ingredientType = IngredientType::query()->create([
            'type_name' => 'Carnes',
        ]);

        $ingredient = Ingredient::query()->create([
            'ingredient_type_id' => $ingredientType->id,
            'ingredient_name' => 'Jamón',
        ]);

        return [
            'category' => $category,
            'ingredient_type' => $ingredientType,
            'ingredient' => $ingredient,
        ];
    }
}
