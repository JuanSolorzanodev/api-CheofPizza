<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\CategorySizePrice;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCategoryPriceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_category_prices(): void
    {
        $this
            ->getJson('/api/v1/admin/catalog/prices')
            ->assertUnauthorized();

        $this
            ->putJson(
                '/api/v1/admin/catalog/prices',
                [
                    'prices' => [],
                ],
            )
            ->assertUnauthorized();
    }

    public function test_operator_cannot_manage_category_prices(): void
    {
        $operator = User::factory()
            ->operator()
            ->create();

        $category = Category::query()->create([
            'category_name' => 'Especiales',
            'description' => null,
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
            ->getJson('/api/v1/admin/catalog/prices')
            ->assertForbidden();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->putJson(
                '/api/v1/admin/catalog/prices',
                [
                    'prices' => [
                        [
                            'category_id' => $category->id,
                            'size_id' => $size->id,
                            'price' => 12.50,
                        ],
                    ],
                ],
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'category_size_prices',
            0,
        );
    }

    public function test_admin_can_list_category_prices_ordered_by_category_and_size(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $specialCategory = Category::query()->create([
            'category_name' => 'Especiales',
            'description' => null,
        ]);

        $traditionalCategory = Category::query()->create([
            'category_name' => 'Tradicionales',
            'description' => null,
        ]);

        $small = Size::query()->create([
            'size_name' => 'Pequeña',
            'portion' => 4,
        ]);

        $large = Size::query()->create([
            'size_name' => 'Familiar',
            'portion' => 12,
        ]);

        $traditionalLarge = CategorySizePrice::query()->create([
            'category_id' => $traditionalCategory->id,
            'size_id' => $large->id,
            'price' => '18.00',
        ]);

        $specialLarge = CategorySizePrice::query()->create([
            'category_id' => $specialCategory->id,
            'size_id' => $large->id,
            'price' => '20.00',
        ]);

        $specialSmall = CategorySizePrice::query()->create([
            'category_id' => $specialCategory->id,
            'size_id' => $small->id,
            'price' => '10.00',
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson('/api/v1/admin/catalog/prices');

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonCount(
                3,
                'data',
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $specialSmall->id,
            )
            ->assertJsonPath(
                'data.0.category.name',
                'Especiales',
            )
            ->assertJsonPath(
                'data.0.size.name',
                'Pequeña',
            )
            ->assertJsonPath(
                'data.0.price',
                10,
            )
            ->assertJsonPath(
                'data.1.id',
                (int) $specialLarge->id,
            )
            ->assertJsonPath(
                'data.1.price',
                20,
            )
            ->assertJsonPath(
                'data.2.id',
                (int) $traditionalLarge->id,
            )
            ->assertJsonPath(
                'data.2.category.name',
                'Tradicionales',
            )
            ->assertJsonPath(
                'message',
                'Precios por categoría recuperados correctamente.',
            );
    }

    public function test_admin_can_create_multiple_category_prices(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $category = Category::query()->create([
            'category_name' => 'Sencillas',
            'description' => null,
        ]);

        $small = Size::query()->create([
            'size_name' => 'Pequeña',
            'portion' => 4,
        ]);

        $medium = Size::query()->create([
            'size_name' => 'Mediana',
            'portion' => 8,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                '/api/v1/admin/catalog/prices',
                [
                    'prices' => [
                        [
                            'category_id' => $category->id,
                            'size_id' => $small->id,
                            'price' => 7.50,
                        ],
                        [
                            'category_id' => $category->id,
                            'size_id' => $medium->id,
                            'price' => 11.25,
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
                'data.0.category_id',
                (int) $category->id,
            )
            ->assertJsonPath(
                'data.0.size_id',
                (int) $small->id,
            )
            ->assertJsonPath(
                'data.0.price',
                7.5,
            )
            ->assertJsonPath(
                'data.1.size_id',
                (int) $medium->id,
            )
            ->assertJsonPath(
                'data.1.price',
                11.25,
            )
            ->assertJsonPath(
                'message',
                'Precios actualizados correctamente.',
            );

        $this->assertDatabaseHas(
            'category_size_prices',
            [
                'category_id' => $category->id,
                'size_id' => $small->id,
                'price' => '7.50',
            ],
        );

        $this->assertDatabaseHas(
            'category_size_prices',
            [
                'category_id' => $category->id,
                'size_id' => $medium->id,
                'price' => '11.25',
            ],
        );
    }

    public function test_admin_can_update_an_existing_category_price(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $category = Category::query()->create([
            'category_name' => 'Especiales',
            'description' => null,
        ]);

        $size = Size::query()->create([
            'size_name' => 'Familiar',
            'portion' => 12,
        ]);

        $price = CategorySizePrice::query()->create([
            'category_id' => $category->id,
            'size_id' => $size->id,
            'price' => '16.00',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                '/api/v1/admin/catalog/prices',
                [
                    'prices' => [
                        [
                            'category_id' => $category->id,
                            'size_id' => $size->id,
                            'price' => 19.75,
                        ],
                    ],
                ],
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data',
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $price->id,
            )
            ->assertJsonPath(
                'data.0.price',
                19.75,
            );

        $this->assertDatabaseHas(
            'category_size_prices',
            [
                'id' => $price->id,
                'category_id' => $category->id,
                'size_id' => $size->id,
                'price' => '19.75',
            ],
        );

        $this->assertDatabaseCount(
            'category_size_prices',
            1,
        );
    }

    public function test_price_equal_to_zero_deletes_the_category_size_relation(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $category = Category::query()->create([
            'category_name' => 'Tradicionales',
            'description' => null,
        ]);

        $size = Size::query()->create([
            'size_name' => 'Grande',
            'portion' => 10,
        ]);

        $price = CategorySizePrice::query()->create([
            'category_id' => $category->id,
            'size_id' => $size->id,
            'price' => '14.00',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                '/api/v1/admin/catalog/prices',
                [
                    'prices' => [
                        [
                            'category_id' => $category->id,
                            'size_id' => $size->id,
                            'price' => 0,
                        ],
                    ],
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data',
                [],
            );

        $this->assertDatabaseMissing(
            'category_size_prices',
            [
                'id' => $price->id,
            ],
        );

        $this->assertDatabaseHas(
            'categories',
            [
                'id' => $category->id,
            ],
        );

        $this->assertDatabaseHas(
            'sizes',
            [
                'id' => $size->id,
            ],
        );
    }

    public function test_repeated_category_and_size_pair_is_rejected(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $category = Category::query()->create([
            'category_name' => 'Especiales',
            'description' => null,
        ]);

        $size = Size::query()->create([
            'size_name' => 'Mediana',
            'portion' => 8,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                '/api/v1/admin/catalog/prices',
                [
                    'prices' => [
                        [
                            'category_id' => $category->id,
                            'size_id' => $size->id,
                            'price' => 10,
                        ],
                        [
                            'category_id' => $category->id,
                            'size_id' => $size->id,
                            'price' => 12,
                        ],
                    ],
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'prices.1',
            ]);

        $errors = $response->json('errors');

        expect(
            $errors['prices.1'] ?? [],
        )->toContain(
            'La combinación de categoría y tamaño está repetida.',
        );

        $this->assertDatabaseCount(
            'category_size_prices',
            0,
        );
    }

    public function test_same_category_can_have_prices_for_different_sizes(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $category = Category::query()->create([
            'category_name' => 'Premium',
            'description' => null,
        ]);

        $small = Size::query()->create([
            'size_name' => 'Pequeña',
            'portion' => 4,
        ]);

        $large = Size::query()->create([
            'size_name' => 'Familiar',
            'portion' => 12,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                '/api/v1/admin/catalog/prices',
                [
                    'prices' => [
                        [
                            'category_id' => $category->id,
                            'size_id' => $small->id,
                            'price' => 9,
                        ],
                        [
                            'category_id' => $category->id,
                            'size_id' => $large->id,
                            'price' => 18,
                        ],
                    ],
                ],
            )
            ->assertOk();

        $this->assertDatabaseCount(
            'category_size_prices',
            2,
        );
    }

    public function test_category_prices_require_existing_relations_and_valid_amounts(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $category = Category::query()->create([
            'category_name' => 'Especiales',
            'description' => null,
        ]);

        $size = Size::query()->create([
            'size_name' => 'Mediana',
            'portion' => 8,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                '/api/v1/admin/catalog/prices',
                [
                    'prices' => [
                        [
                            'category_id' => 999999,
                            'size_id' => $size->id,
                            'price' => -1,
                        ],
                        [
                            'category_id' => $category->id,
                            'size_id' => 999999,
                            'price' => 1.999,
                        ],
                    ],
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'prices.0.category_id',
                'prices.0.price',
                'prices.1.size_id',
                'prices.1.price',
            ]);

        $errors = $response->json('errors');

        expect(
            $errors['prices.0.price'] ?? [],
        )->toContain(
            'El precio no puede ser negativo.',
        );

        expect(
            $errors['prices.1.price'] ?? [],
        )->toContain(
            'El precio debe tener máximo dos decimales.',
        );

        $this->assertDatabaseCount(
            'category_size_prices',
            0,
        );
    }

    public function test_invalid_batch_does_not_modify_existing_prices(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $category = Category::query()->create([
            'category_name' => 'Sencillas',
            'description' => null,
        ]);

        $small = Size::query()->create([
            'size_name' => 'Pequeña',
            'portion' => 4,
        ]);

        $medium = Size::query()->create([
            'size_name' => 'Mediana',
            'portion' => 8,
        ]);

        $existingPrice = CategorySizePrice::query()->create([
            'category_id' => $category->id,
            'size_id' => $small->id,
            'price' => '8.00',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                '/api/v1/admin/catalog/prices',
                [
                    'prices' => [
                        [
                            'category_id' => $category->id,
                            'size_id' => $small->id,
                            'price' => 10,
                        ],
                        [
                            'category_id' => $category->id,
                            'size_id' => $medium->id,
                            'price' => -5,
                        ],
                    ],
                ],
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas(
            'category_size_prices',
            [
                'id' => $existingPrice->id,
                'category_id' => $category->id,
                'size_id' => $small->id,
                'price' => '8.00',
            ],
        );

        $this->assertDatabaseMissing(
            'category_size_prices',
            [
                'category_id' => $category->id,
                'size_id' => $medium->id,
            ],
        );

        $this->assertDatabaseCount(
            'category_size_prices',
            1,
        );
    }

    public function test_prices_field_is_required_and_cannot_be_empty(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                '/api/v1/admin/catalog/prices',
                [],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'prices',
            ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                '/api/v1/admin/catalog/prices',
                [
                    'prices' => [],
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'prices',
            ]);

        $this->assertDatabaseCount(
            'category_size_prices',
            0,
        );
    }
}
