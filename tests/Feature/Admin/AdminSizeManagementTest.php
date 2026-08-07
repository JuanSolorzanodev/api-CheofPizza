<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartStatus;
use App\Models\Category;
use App\Models\CategorySizePrice;
use App\Models\Ingredient;
use App\Models\IngredientSizePrice;
use App\Models\IngredientType;
use App\Models\Pizza;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminSizeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_sizes(): void
    {
        $this
            ->getJson('/api/v1/admin/catalog/sizes')
            ->assertUnauthorized();
    }

    public function test_operator_cannot_manage_sizes(): void
    {
        $operator = User::factory()
            ->operator()
            ->create();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->getJson('/api/v1/admin/catalog/sizes')
            ->assertForbidden();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/sizes',
                [
                    'name' => 'Tamaño no permitido',
                    'portion' => 8,
                ],
            )
            ->assertForbidden();

        $this->assertDatabaseMissing(
            'sizes',
            [
                'size_name' => 'Tamaño no permitido',
            ],
        );
    }

    public function test_admin_can_list_sizes_with_relation_counts(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $size = Size::query()->create([
            'size_name' => 'Mediana',
            'portion' => 8,
        ]);

        $category = Category::query()->create([
            'category_name' => 'Especiales',
            'description' => null,
        ]);

        CategorySizePrice::query()->create([
            'category_id' => $category->id,
            'size_id' => $size->id,
            'price' => '12.50',
        ]);

        $ingredientType = IngredientType::query()->create([
            'type_name' => 'Extras',
        ]);

        $ingredient = Ingredient::query()->create([
            'ingredient_type_id' => $ingredientType->id,
            'ingredient_name' => 'Queso extra',
        ]);

        IngredientSizePrice::query()->create([
            'ingredient_id' => $ingredient->id,
            'size_id' => $size->id,
            'extra_price' => '1.50',
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson('/api/v1/admin/catalog/sizes');

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $size->id,
            )
            ->assertJsonPath(
                'data.0.name',
                'Mediana',
            )
            ->assertJsonPath(
                'data.0.portion',
                8,
            )
            ->assertJsonPath(
                'data.0.category_prices_count',
                1,
            )
            ->assertJsonPath(
                'data.0.ingredient_prices_count',
                1,
            )
            ->assertJsonPath(
                'data.0.cart_items_count',
                0,
            )
            ->assertJsonPath(
                'data.0.order_items_count',
                0,
            );
    }

    public function test_admin_can_create_size_with_normalized_name(): void
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
                '/api/v1/admin/catalog/sizes',
                [
                    'name' => '  Familiar  ',
                    'portion' => 12,
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
                'Familiar',
            )
            ->assertJsonPath(
                'data.portion',
                12,
            )
            ->assertJsonPath(
                'data.category_prices_count',
                0,
            )
            ->assertJsonPath(
                'data.ingredient_prices_count',
                0,
            )
            ->assertJsonPath(
                'data.cart_items_count',
                0,
            )
            ->assertJsonPath(
                'data.order_items_count',
                0,
            );

        $this->assertDatabaseHas(
            'sizes',
            [
                'size_name' => 'Familiar',
                'portion' => 12,
            ],
        );
    }

    public function test_size_name_must_be_unique(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        Size::query()->create([
            'size_name' => 'Pequeña',
            'portion' => 4,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/sizes',
                [
                    'name' => 'Pequeña',
                    'portion' => 6,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);

        $this->assertSame(
            1,
            Size::query()
                ->where(
                    'size_name',
                    'Pequeña',
                )
                ->count(),
        );
    }

    public function test_size_portion_must_be_between_one_and_one_hundred(): void
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
                '/api/v1/admin/catalog/sizes',
                [
                    'name' => 'Inválido mínimo',
                    'portion' => 0,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'portion',
            ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/sizes',
                [
                    'name' => 'Inválido máximo',
                    'portion' => 101,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'portion',
            ]);

        $this->assertDatabaseCount(
            'sizes',
            0,
        );
    }

    public function test_admin_can_view_and_update_size(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $size = Size::query()->create([
            'size_name' => 'Mediana',
            'portion' => 8,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                "/api/v1/admin/catalog/sizes/{$size->id}",
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                (int) $size->id,
            )
            ->assertJsonPath(
                'data.name',
                'Mediana',
            )
            ->assertJsonPath(
                'data.portion',
                8,
            );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                "/api/v1/admin/catalog/sizes/{$size->id}",
                [
                    'name' => '  Grande  ',
                    'portion' => 10,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Grande',
            )
            ->assertJsonPath(
                'data.portion',
                10,
            );

        $this->assertDatabaseHas(
            'sizes',
            [
                'id' => $size->id,
                'size_name' => 'Grande',
                'portion' => 10,
            ],
        );
    }

    public function test_updating_size_can_keep_its_own_name(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $size = Size::query()->create([
            'size_name' => 'Familiar',
            'portion' => 12,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                "/api/v1/admin/catalog/sizes/{$size->id}",
                [
                    'name' => 'Familiar',
                    'portion' => 14,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Familiar',
            )
            ->assertJsonPath(
                'data.portion',
                14,
            );

        $this->assertDatabaseHas(
            'sizes',
            [
                'id' => $size->id,
                'size_name' => 'Familiar',
                'portion' => 14,
            ],
        );
    }

    public function test_admin_cannot_delete_size_used_in_cart_items(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $size = Size::query()->create([
            'size_name' => 'Mediana',
            'portion' => 8,
        ]);

        $this->mockSizeCartItemRelation(
            $size,
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->deleteJson(
                "/api/v1/admin/catalog/sizes/{$size->id}",
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'size',
            ])
            ->assertJsonPath(
                'errors.size.0',
                'No puedes eliminar este tamaño porque está utilizado en uno o más carritos.',
            );

        $this->assertDatabaseHas(
            'sizes',
            [
                'id' => $size->id,
            ],
        );
    }

    public function test_deleting_unused_size_also_deletes_related_prices(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $size = Size::query()->create([
            'size_name' => 'Eliminable',
            'portion' => 6,
        ]);

        $category = Category::query()->create([
            'category_name' => 'Categoría de prueba',
            'description' => null,
        ]);

        $categoryPrice = CategorySizePrice::query()->create([
            'category_id' => $category->id,
            'size_id' => $size->id,
            'price' => '9.50',
        ]);

        $ingredientType = IngredientType::query()->create([
            'type_name' => 'Ingrediente de prueba',
        ]);

        $ingredient = Ingredient::query()->create([
            'ingredient_type_id' => $ingredientType->id,
            'ingredient_name' => 'Ingrediente eliminable',
        ]);

        $ingredientPrice = IngredientSizePrice::query()->create([
            'ingredient_id' => $ingredient->id,
            'size_id' => $size->id,
            'extra_price' => '1.25',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->deleteJson(
                "/api/v1/admin/catalog/sizes/{$size->id}",
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
                'Tamaño eliminado correctamente.',
            );

        $this->assertDatabaseMissing(
            'sizes',
            [
                'id' => $size->id,
            ],
        );

        $this->assertDatabaseMissing(
            'category_size_prices',
            [
                'id' => $categoryPrice->id,
            ],
        );

        $this->assertDatabaseMissing(
            'ingredient_size_prices',
            [
                'id' => $ingredientPrice->id,
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
                'id' => $ingredient->id,
            ],
        );
    }

    /**
     * Crea únicamente las relaciones mínimas requeridas para que
     * el tamaño aparezca utilizado en un carrito.
     */
    private function mockSizeCartItemRelation(
        Size $size,
    ): void {
        $customer = User::factory()
            ->customer()
            ->create();

        $activeStatusId = CartStatus::query()
            ->firstOrCreate([
                'status_name' => 'active',
            ])
            ->id;

        $category = Category::query()->create([
            'category_name' => 'Categoría carrito',
            'description' => null,
        ]);

        $pizza = Pizza::query()->create([
            'category_id' => $category->id,
            'pizza_name' => 'Pizza carrito',
            'description' => null,
            'image_url' => null,
            'is_visible' => true,
        ]);

        $cart = Cart::query()->create([
            'user_id' => $customer->id,
            'cart_status_id' => $activeStatusId,
            'session_id' => null,
            'total' => '10.00',
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
            'unit_price' => '10.00',
            'subtotal' => '10.00',
        ]);
    }
}
