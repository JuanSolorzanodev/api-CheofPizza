<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\CategorySizePrice;
use App\Models\Pizza;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_categories(): void
    {
        $this
            ->getJson('/api/v1/admin/catalog/categories')
            ->assertUnauthorized();
    }

    public function test_operator_cannot_manage_categories(): void
    {
        $operator = User::factory()
            ->operator()
            ->create();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->getJson('/api/v1/admin/catalog/categories')
            ->assertForbidden();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/categories',
                [
                    'name' => 'Categoría no permitida',
                    'description' => null,
                ],
            )
            ->assertForbidden();

        $this->assertDatabaseMissing(
            'categories',
            [
                'category_name' => 'Categoría no permitida',
            ],
        );
    }

    public function test_admin_can_list_categories_with_counts_and_prices(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $category = Category::query()->create([
            'category_name' => 'Especiales',
            'description' => 'Pizzas especiales.',
        ]);

        $size = Size::query()->create([
            'size_name' => 'Mediana',
            'portion' => 8,
        ]);

        CategorySizePrice::query()->create([
            'category_id' => $category->id,
            'size_id' => $size->id,
            'price' => '12.50',
        ]);

        Pizza::query()->create([
            'category_id' => $category->id,
            'pizza_name' => 'Pizza Especial',
            'description' => 'Pizza de prueba.',
            'image_url' => null,
            'is_visible' => true,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson('/api/v1/admin/catalog/categories');

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $category->id,
            )
            ->assertJsonPath(
                'data.0.name',
                'Especiales',
            )
            ->assertJsonPath(
                'data.0.pizzas_count',
                1,
            )
            ->assertJsonPath(
                'data.0.prices_count',
                1,
            )
            ->assertJsonPath(
                'data.0.size_prices.0.size.id',
                (int) $size->id,
            )
            ->assertJsonPath(
                'data.0.size_prices.0.price',
                12.5,
            );
    }

    public function test_admin_can_create_category_with_normalized_data(): void
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
                '/api/v1/admin/catalog/categories',
                [
                    'name' => '  Premium  ',
                    'description' => '  Categoría premium.  ',
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
                'Premium',
            )
            ->assertJsonPath(
                'data.description',
                'Categoría premium.',
            )
            ->assertJsonPath(
                'data.pizzas_count',
                0,
            )
            ->assertJsonPath(
                'data.prices_count',
                0,
            );

        $this->assertDatabaseHas(
            'categories',
            [
                'category_name' => 'Premium',
                'description' => 'Categoría premium.',
            ],
        );
    }

    public function test_category_name_must_be_unique(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        Category::query()->create([
            'category_name' => 'Tradicionales',
            'description' => null,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/catalog/categories',
                [
                    'name' => 'Tradicionales',
                    'description' => 'Nombre duplicado.',
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);

        $this->assertSame(
            1,
            Category::query()
                ->where(
                    'category_name',
                    'Tradicionales',
                )
                ->count(),
        );
    }

    public function test_admin_can_view_and_update_category(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $category = Category::query()->create([
            'category_name' => 'Sencillas',
            'description' => 'Descripción anterior.',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                "/api/v1/admin/catalog/categories/{$category->id}",
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                (int) $category->id,
            )
            ->assertJsonPath(
                'data.name',
                'Sencillas',
            );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                "/api/v1/admin/catalog/categories/{$category->id}",
                [
                    'name' => '  Clásicas  ',
                    'description' => '  Descripción actualizada.  ',
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Clásicas',
            )
            ->assertJsonPath(
                'data.description',
                'Descripción actualizada.',
            );

        $this->assertDatabaseHas(
            'categories',
            [
                'id' => $category->id,
                'category_name' => 'Clásicas',
                'description' => 'Descripción actualizada.',
            ],
        );
    }

    public function test_admin_cannot_delete_category_with_pizzas(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $category = Category::query()->create([
            'category_name' => 'Con pizzas',
            'description' => null,
        ]);

        Pizza::query()->create([
            'category_id' => $category->id,
            'pizza_name' => 'Pizza relacionada',
            'description' => null,
            'image_url' => null,
            'is_visible' => true,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->deleteJson(
                "/api/v1/admin/catalog/categories/{$category->id}",
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category',
            ])
            ->assertJsonPath(
                'errors.category.0',
                'No puedes eliminar esta categoría porque tiene pizzas asociadas.',
            );

        $this->assertDatabaseHas(
            'categories',
            [
                'id' => $category->id,
            ],
        );

        $this->assertDatabaseHas(
            'pizzas',
            [
                'category_id' => $category->id,
            ],
        );
    }

    public function test_deleting_unused_category_also_deletes_its_prices(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $category = Category::query()->create([
            'category_name' => 'Categoría eliminable',
            'description' => null,
        ]);

        $size = Size::query()->create([
            'size_name' => 'Familiar',
            'portion' => 12,
        ]);

        $price = CategorySizePrice::query()->create([
            'category_id' => $category->id,
            'size_id' => $size->id,
            'price' => '18.00',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->deleteJson(
                "/api/v1/admin/catalog/categories/{$category->id}",
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
                'Categoría eliminada correctamente.',
            );

        $this->assertDatabaseMissing(
            'categories',
            [
                'id' => $category->id,
            ],
        );

        $this->assertDatabaseMissing(
            'category_size_prices',
            [
                'id' => $price->id,
            ],
        );

        $this->assertDatabaseHas(
            'sizes',
            [
                'id' => $size->id,
            ],
        );
    }

    public function test_updating_category_can_keep_its_own_name(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $category = Category::query()->create([
            'category_name' => 'Favoritas',
            'description' => 'Descripción inicial.',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                "/api/v1/admin/catalog/categories/{$category->id}",
                [
                    'name' => 'Favoritas',
                    'description' => 'Descripción modificada.',
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Favoritas',
            )
            ->assertJsonPath(
                'data.description',
                'Descripción modificada.',
            );

        $this->assertDatabaseHas(
            'categories',
            [
                'id' => $category->id,
                'category_name' => 'Favoritas',
                'description' => 'Descripción modificada.',
            ],
        );
    }
}
