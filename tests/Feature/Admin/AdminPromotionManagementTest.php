<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Promotion;
use App\Models\PromotionDetail;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminPromotionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_promotions(): void
    {
        $this
            ->getJson('/api/v1/admin/promotions')
            ->assertUnauthorized();

        $this
            ->postJson(
                '/api/v1/admin/promotions',
                [],
            )
            ->assertUnauthorized();
    }

    public function test_operator_cannot_manage_promotions(): void
    {
        $operator = User::factory()
            ->operator()
            ->create();

        [
            'category' => $category,
            'small' => $small,
        ] = $this->promotionCatalogFixture();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->getJson('/api/v1/admin/promotions')
            ->assertForbidden();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/promotions',
                $this->fixedComboPayload(
                    category: $category,
                    size: $small,
                ),
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'promotions',
            0,
        );

        $this->assertDatabaseCount(
            'promotion_details',
            0,
        );
    }

    public function test_admin_can_create_fixed_combo_promotion(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $category,
            'small' => $small,
        ] = $this->promotionCatalogFixture();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/promotions',
                $this->fixedComboPayload(
                    category: $category,
                    size: $small,
                    overrides: [
                        'name' => '  Combo Familiar  ',
                        'slug' => 'combo-familiar',
                        'description' => '  Dos pizzas pequeñas.  ',
                        'banner_image_url' => '  https://example.com/combo.jpg  ',
                    ],
                ),
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.name',
                'Combo Familiar',
            )
            ->assertJsonPath(
                'data.slug',
                'combo-familiar',
            )
            ->assertJsonPath(
                'data.description',
                'Dos pizzas pequeñas.',
            )
            ->assertJsonPath(
                'data.banner_image_url',
                'https://example.com/combo.jpg',
            )
            ->assertJsonPath(
                'data.type',
                Promotion::TYPE_FIXED_COMBO,
            )
            ->assertJsonPath(
                'data.selection_quantity',
                2,
            )
            ->assertJsonPath(
                'data.price',
                15.5,
            )
            ->assertJsonPath(
                'data.is_active',
                false,
            )
            ->assertJsonPath(
                'data.status',
                'inactive',
            )
            ->assertJsonCount(
                1,
                'data.details',
            )
            ->assertJsonPath(
                'data.details.0.category_id',
                (int) $category->id,
            )
            ->assertJsonPath(
                'data.details.0.size_id',
                (int) $small->id,
            )
            ->assertJsonPath(
                'data.details.0.required_quantity',
                2,
            )
            ->assertJsonPath(
                'data.size_prices',
                [],
            )
            ->assertJsonPath(
                'data.can_delete',
                true,
            )
            ->assertJsonPath(
                'message',
                'Promoción creada correctamente.',
            );

        $promotion = Promotion::query()
            ->where(
                'slug',
                'combo-familiar',
            )
            ->firstOrFail();

        $this->assertDatabaseHas(
            'promotions',
            [
                'id' => $promotion->id,
                'promotion_name' => 'Combo Familiar',
                'slug' => 'combo-familiar',
                'description' => 'Dos pizzas pequeñas.',
                'banner_image_url' => 'https://example.com/combo.jpg',
                'promotion_type' => Promotion::TYPE_FIXED_COMBO,
                'selection_quantity' => 2,
                'promotion_price' => '15.50',
                'is_active' => false,
            ],
        );

        $this->assertDatabaseHas(
            'promotion_details',
            [
                'promotion_id' => $promotion->id,
                'category_id' => $category->id,
                'size_id' => $small->id,
                'required_quantity' => 2,
            ],
        );

        $this->assertDatabaseCount(
            'promotion_details',
            1,
        );

        $this->assertDatabaseCount(
            'promotion_size_prices',
            0,
        );
    }

    public function test_admin_can_create_size_fixed_price_promotion(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'small' => $small,
            'medium' => $medium,
        ] = $this->promotionCatalogFixture();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/promotions',
                $this->sizeFixedPricePayload(
                    small: $small,
                    medium: $medium,
                ),
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Martes de promoción',
            )
            ->assertJsonPath(
                'data.slug',
                'martes-promocion',
            )
            ->assertJsonPath(
                'data.type',
                Promotion::TYPE_SIZE_FIXED_PRICE,
            )
            ->assertJsonPath(
                'data.selection_quantity',
                1,
            )
            ->assertJsonPath(
                'data.price',
                0,
            )
            ->assertJsonCount(
                2,
                'data.size_prices',
            )
            ->assertJsonPath(
                'data.size_prices.0.size_id',
                (int) $small->id,
            )
            ->assertJsonPath(
                'data.size_prices.0.price',
                6.5,
            )
            ->assertJsonPath(
                'data.size_prices.1.size_id',
                (int) $medium->id,
            )
            ->assertJsonPath(
                'data.size_prices.1.price',
                9.75,
            )
            ->assertJsonPath(
                'data.details',
                [],
            );

        $promotion = Promotion::query()
            ->where(
                'slug',
                'martes-promocion',
            )
            ->firstOrFail();

        $this->assertDatabaseHas(
            'promotions',
            [
                'id' => $promotion->id,
                'promotion_type' => Promotion::TYPE_SIZE_FIXED_PRICE,
                'promotion_price' => '0.00',
                'selection_quantity' => 1,
            ],
        );

        $this->assertDatabaseHas(
            'promotion_size_prices',
            [
                'promotion_id' => $promotion->id,
                'size_id' => $small->id,
                'fixed_price' => '6.50',
            ],
        );

        $this->assertDatabaseHas(
            'promotion_size_prices',
            [
                'promotion_id' => $promotion->id,
                'size_id' => $medium->id,
                'fixed_price' => '9.75',
            ],
        );

        $this->assertDatabaseCount(
            'promotion_size_prices',
            2,
        );

        $this->assertDatabaseCount(
            'promotion_details',
            0,
        );
    }

    public function test_promotion_name_and_slug_must_be_unique(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $category,
            'small' => $small,
        ] = $this->promotionCatalogFixture();

        Promotion::query()->create([
            'promotion_name' => 'Combo existente',
            'slug' => 'combo-existente',
            'description' => null,
            'banner_image_url' => null,
            'promotion_type' => Promotion::TYPE_FIXED_COMBO,
            'selection_quantity' => 2,
            'promotion_price' => '12.00',
            'starts_at' => '2026-08-10 00:00:00',
            'ends_at' => '2026-08-20 23:59:59',
            'is_active' => false,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/promotions',
                $this->fixedComboPayload(
                    category: $category,
                    size: $small,
                    overrides: [
                        'name' => 'Combo existente',
                        'slug' => 'combo-existente',
                    ],
                ),
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'slug',
            ])
            ->assertJsonPath(
                'errors.name.0',
                'Ya existe una promoción con este nombre.',
            )
            ->assertJsonPath(
                'errors.slug.0',
                'Ya existe una promoción con este slug.',
            );

        $this->assertDatabaseCount(
            'promotions',
            1,
        );

        $this->assertDatabaseCount(
            'promotion_details',
            0,
        );
    }

    public function test_fixed_combo_requires_valid_details_price_and_quantity_sum(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $category,
            'small' => $small,
            'medium' => $medium,
        ] = $this->promotionCatalogFixture();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/promotions',
                $this->fixedComboPayload(
                    category: $category,
                    size: $small,
                    overrides: [
                        'price' => 0,
                        'selection_quantity' => 5,
                        'details' => [
                            [
                                'category_id' => $category->id,
                                'size_id' => $small->id,
                                'required_quantity' => 1,
                            ],
                            [
                                'category_id' => $category->id,
                                'size_id' => $medium->id,
                                'required_quantity' => 1,
                            ],
                        ],
                    ],
                ),
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'price',
                'details',
                'selection_quantity',
            ]);

        $errors = $response->json('errors');

        expect(
            $errors['price'] ?? [],
        )->toContain(
            'El combo debe tener un precio mayor a cero.',
        );

        expect(
            $errors['details'] ?? [],
        )->toContain(
            'Todas las reglas del combo deben utilizar el mismo tamaño.',
        );

        expect(
            $errors['selection_quantity'] ?? [],
        )->toContain(
            'La cantidad de selección debe coincidir con la suma de las reglas.',
        );

        $this->assertDatabaseCount(
            'promotions',
            0,
        );

        $this->assertDatabaseCount(
            'promotion_details',
            0,
        );
    }

    public function test_fixed_combo_rejects_repeated_category_and_size(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $category,
            'small' => $small,
        ] = $this->promotionCatalogFixture();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/promotions',
                $this->fixedComboPayload(
                    category: $category,
                    size: $small,
                    overrides: [
                        'details' => [
                            [
                                'category_id' => $category->id,
                                'size_id' => $small->id,
                                'required_quantity' => 1,
                            ],
                            [
                                'category_id' => $category->id,
                                'size_id' => $small->id,
                                'required_quantity' => 1,
                            ],
                        ],
                    ],
                ),
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'details',
            ]);

        $errors = $response->json('errors');

        expect(
            $errors['details'] ?? [],
        )->toContain(
            'No puedes repetir una misma categoría y tamaño.',
        );

        $this->assertDatabaseCount(
            'promotions',
            0,
        );
    }

    public function test_size_fixed_price_requires_prices_and_rejects_repeated_sizes(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'small' => $small,
            'medium' => $medium,
        ] = $this->promotionCatalogFixture();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/promotions',
                $this->sizeFixedPricePayload(
                    small: $small,
                    medium: $medium,
                    overrides: [
                        'size_prices' => [],
                    ],
                ),
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'size_prices',
            ])
            ->assertJsonPath(
                'errors.size_prices.0',
                'Configura al menos un precio por tamaño.',
            );

        $duplicateResponse = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/promotions',
                $this->sizeFixedPricePayload(
                    small: $small,
                    medium: $medium,
                    overrides: [
                        'size_prices' => [
                            [
                                'size_id' => $small->id,
                                'price' => 6.50,
                            ],
                            [
                                'size_id' => $small->id,
                                'price' => 7.50,
                            ],
                        ],
                    ],
                ),
            );

        $duplicateResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'size_prices.0.size_id',
                'size_prices.1.size_id',
            ]);

        $errors = $duplicateResponse->json('errors');

        expect(
            $errors['size_prices.0.size_id'] ?? [],
        )->toContain(
            'No puedes repetir tamaños.',
        );

        expect(
            $errors['size_prices.1.size_id'] ?? [],
        )->toContain(
            'No puedes repetir tamaños.',
        );

        $this->assertDatabaseCount(
            'promotions',
            0,
        );

        $this->assertDatabaseCount(
            'promotion_size_prices',
            0,
        );
    }

    public function test_admin_can_view_and_update_promotion_switching_configuration_type(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $category,
            'small' => $small,
            'medium' => $medium,
        ] = $this->promotionCatalogFixture();

        $promotion = Promotion::query()->create([
            'promotion_name' => 'Combo inicial',
            'slug' => 'combo-inicial',
            'description' => 'Descripción inicial.',
            'banner_image_url' => null,
            'promotion_type' => Promotion::TYPE_FIXED_COMBO,
            'selection_quantity' => 2,
            'promotion_price' => '14.00',
            'starts_at' => '2026-08-10 00:00:00',
            'ends_at' => '2026-08-20 23:59:59',
            'is_active' => false,
        ]);

        $detail = PromotionDetail::query()->create([
            'promotion_id' => $promotion->id,
            'category_id' => $category->id,
            'size_id' => $small->id,
            'required_quantity' => 2,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                "/api/v1/admin/promotions/{$promotion->id}",
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                (int) $promotion->id,
            )
            ->assertJsonPath(
                'data.type',
                Promotion::TYPE_FIXED_COMBO,
            )
            ->assertJsonCount(
                1,
                'data.details',
            )
            ->assertJsonPath(
                'data.details.0.id',
                (int) $detail->id,
            );

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                "/api/v1/admin/promotions/{$promotion->id}",
                $this->sizeFixedPricePayload(
                    small: $small,
                    medium: $medium,
                    overrides: [
                        'name' => 'Promoción actualizada',
                        'slug' => 'promocion-actualizada',
                        'description' => 'Nueva configuración.',
                    ],
                ),
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                (int) $promotion->id,
            )
            ->assertJsonPath(
                'data.name',
                'Promoción actualizada',
            )
            ->assertJsonPath(
                'data.slug',
                'promocion-actualizada',
            )
            ->assertJsonPath(
                'data.type',
                Promotion::TYPE_SIZE_FIXED_PRICE,
            )
            ->assertJsonPath(
                'data.price',
                0,
            )
            ->assertJsonPath(
                'data.details',
                [],
            )
            ->assertJsonCount(
                2,
                'data.size_prices',
            )
            ->assertJsonPath(
                'message',
                'Promoción actualizada correctamente.',
            );

        $this->assertDatabaseHas(
            'promotions',
            [
                'id' => $promotion->id,
                'promotion_name' => 'Promoción actualizada',
                'slug' => 'promocion-actualizada',
                'description' => 'Nueva configuración.',
                'promotion_type' => Promotion::TYPE_SIZE_FIXED_PRICE,
                'promotion_price' => '0.00',
            ],
        );

        $this->assertDatabaseMissing(
            'promotion_details',
            [
                'id' => $detail->id,
            ],
        );

        $this->assertDatabaseHas(
            'promotion_size_prices',
            [
                'promotion_id' => $promotion->id,
                'size_id' => $small->id,
                'fixed_price' => '6.50',
            ],
        );

        $this->assertDatabaseHas(
            'promotion_size_prices',
            [
                'promotion_id' => $promotion->id,
                'size_id' => $medium->id,
                'fixed_price' => '9.75',
            ],
        );
    }

    public function test_invalid_update_does_not_modify_promotion_configuration(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $category,
            'small' => $small,
        ] = $this->promotionCatalogFixture();

        $promotion = Promotion::query()->create([
            'promotion_name' => 'Promoción original',
            'slug' => 'promocion-original',
            'description' => 'Descripción original.',
            'banner_image_url' => null,
            'promotion_type' => Promotion::TYPE_FIXED_COMBO,
            'selection_quantity' => 2,
            'promotion_price' => '16.00',
            'starts_at' => '2026-08-10 00:00:00',
            'ends_at' => '2026-08-20 23:59:59',
            'is_active' => false,
        ]);

        $detail = PromotionDetail::query()->create([
            'promotion_id' => $promotion->id,
            'category_id' => $category->id,
            'size_id' => $small->id,
            'required_quantity' => 2,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->putJson(
                "/api/v1/admin/promotions/{$promotion->id}",
                $this->fixedComboPayload(
                    category: $category,
                    size: $small,
                    overrides: [
                        'name' => 'No debe guardarse',
                        'slug' => 'Slug Inválido',
                        'description' => 'Tampoco debe guardarse.',
                    ],
                ),
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);

        $this->assertDatabaseHas(
            'promotions',
            [
                'id' => $promotion->id,
                'promotion_name' => 'Promoción original',
                'slug' => 'promocion-original',
                'description' => 'Descripción original.',
                'promotion_type' => Promotion::TYPE_FIXED_COMBO,
                'promotion_price' => '16.00',
            ],
        );

        $this->assertDatabaseHas(
            'promotion_details',
            [
                'id' => $detail->id,
                'promotion_id' => $promotion->id,
                'category_id' => $category->id,
                'size_id' => $small->id,
                'required_quantity' => 2,
            ],
        );

        $this->assertDatabaseCount(
            'promotion_details',
            1,
        );

        $this->assertDatabaseCount(
            'promotion_size_prices',
            0,
        );
    }

    /**
     * @return array{
     *     category: Category,
     *     small: Size,
     *     medium: Size
     * }
     */
    private function promotionCatalogFixture(): array
    {
        $category = Category::query()->create([
            'category_name' => 'Especiales',
            'description' => 'Categoría de prueba.',
        ]);

        $small = Size::query()->create([
            'size_name' => 'Pequeña',
            'portion' => 4,
        ]);

        $medium = Size::query()->create([
            'size_name' => 'Mediana',
            'portion' => 8,
        ]);

        return [
            'category' => $category,
            'small' => $small,
            'medium' => $medium,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function fixedComboPayload(
        Category $category,
        Size $size,
        array $overrides = [],
    ): array {
        return array_replace(
            [
                'name' => 'Combo de prueba',
                'slug' => 'combo-prueba',
                'description' => 'Promoción de prueba.',
                'banner_image_url' => 'https://example.com/combo.jpg',
                'type' => Promotion::TYPE_FIXED_COMBO,
                'selection_quantity' => 2,
                'price' => 15.50,
                'starts_at' => '2026-08-10 00:00:00',
                'ends_at' => '2026-08-20 23:59:59',
                'is_active' => false,
                'details' => [
                    [
                        'category_id' => $category->id,
                        'size_id' => $size->id,
                        'required_quantity' => 2,
                    ],
                ],
                'size_prices' => [],
            ],
            $overrides,
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sizeFixedPricePayload(
        Size $small,
        Size $medium,
        array $overrides = [],
    ): array {
        return array_replace(
            [
                'name' => 'Martes de promoción',
                'slug' => 'martes-promocion',
                'description' => 'Precio fijo según tamaño.',
                'banner_image_url' => 'https://example.com/martes.jpg',
                'type' => Promotion::TYPE_SIZE_FIXED_PRICE,
                'selection_quantity' => 1,
                'price' => null,
                'starts_at' => '2026-08-10 00:00:00',
                'ends_at' => '2026-08-20 23:59:59',
                'is_active' => false,
                'details' => [],
                'size_prices' => [
                    [
                        'size_id' => $small->id,
                        'price' => 6.50,
                    ],
                    [
                        'size_id' => $medium->id,
                        'price' => 9.75,
                    ],
                ],
            ],
            $overrides,
        );
    }
}
