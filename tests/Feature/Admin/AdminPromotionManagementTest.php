<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartStatus;
use App\Models\Category;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\Promotion;
use App\Models\PromotionDetail;
use App\Models\Size;
use App\Models\User;
use Carbon\CarbonImmutable;
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

    public function test_admin_can_activate_and_deactivate_a_valid_promotion(): void
    {
        CarbonImmutable::setTestNow(
            '2026-08-12 12:00:00',
        );

        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $category,
            'small' => $small,
        ] = $this->promotionCatalogFixture();

        $promotion = Promotion::query()->create([
            'promotion_name' => 'Combo activable',
            'slug' => 'combo-activable',
            'description' => null,
            'banner_image_url' => null,
            'promotion_type' => Promotion::TYPE_FIXED_COMBO,
            'selection_quantity' => 2,
            'promotion_price' => '15.00',
            'starts_at' => '2026-08-10 00:00:00',
            'ends_at' => '2026-08-20 23:59:59',
            'is_active' => false,
        ]);

        PromotionDetail::query()->create([
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
            ->patchJson(
                "/api/v1/admin/promotions/{$promotion->id}/visibility",
                [
                    'is_active' => true,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.is_active',
                true,
            )
            ->assertJsonPath(
                'data.status',
                'active',
            )
            ->assertJsonPath(
                'message',
                'Promoción activada correctamente.',
            );

        $this->assertDatabaseHas(
            'promotions',
            [
                'id' => $promotion->id,
                'is_active' => true,
            ],
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->patchJson(
                "/api/v1/admin/promotions/{$promotion->id}/visibility",
                [
                    'is_active' => false,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.is_active',
                false,
            )
            ->assertJsonPath(
                'data.status',
                'inactive',
            )
            ->assertJsonPath(
                'message',
                'Promoción desactivada correctamente.',
            );

        $this->assertDatabaseHas(
            'promotions',
            [
                'id' => $promotion->id,
                'is_active' => false,
            ],
        );

        CarbonImmutable::setTestNow();
    }

    public function test_fixed_combo_cannot_be_activated_without_rules(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $promotion = Promotion::query()->create([
            'promotion_name' => 'Combo incompleto',
            'slug' => 'combo-incompleto',
            'description' => null,
            'banner_image_url' => null,
            'promotion_type' => Promotion::TYPE_FIXED_COMBO,
            'selection_quantity' => 2,
            'promotion_price' => '15.00',
            'starts_at' => '2026-08-10 00:00:00',
            'ends_at' => '2026-08-20 23:59:59',
            'is_active' => false,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->patchJson(
                "/api/v1/admin/promotions/{$promotion->id}/visibility",
                [
                    'is_active' => true,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'is_active',
            ])
            ->assertJsonPath(
                'errors.is_active.0',
                'El combo necesita reglas y un precio válido antes de activarse.',
            );

        $this->assertDatabaseHas(
            'promotions',
            [
                'id' => $promotion->id,
                'is_active' => false,
            ],
        );
    }

    public function test_size_fixed_price_promotion_cannot_be_activated_without_prices(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $promotion = Promotion::query()->create([
            'promotion_name' => 'Promoción sin precios',
            'slug' => 'promocion-sin-precios',
            'description' => null,
            'banner_image_url' => null,
            'promotion_type' => Promotion::TYPE_SIZE_FIXED_PRICE,
            'selection_quantity' => 1,
            'promotion_price' => '0.00',
            'starts_at' => '2026-08-10 00:00:00',
            'ends_at' => '2026-08-20 23:59:59',
            'is_active' => false,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->patchJson(
                "/api/v1/admin/promotions/{$promotion->id}/visibility",
                [
                    'is_active' => true,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'is_active',
            ])
            ->assertJsonPath(
                'errors.is_active.0',
                'Configura al menos un precio por tamaño antes de activar la promoción.',
            );

        $this->assertDatabaseHas(
            'promotions',
            [
                'id' => $promotion->id,
                'is_active' => false,
            ],
        );
    }

    public function test_active_promotion_with_future_start_date_is_scheduled(): void
    {
        CarbonImmutable::setTestNow(
            '2026-08-05 12:00:00',
        );

        $admin = User::factory()
            ->admin()
            ->create();

        $promotion = Promotion::query()->create([
            'promotion_name' => 'Promoción programada',
            'slug' => 'promocion-programada',
            'description' => null,
            'banner_image_url' => null,
            'promotion_type' => Promotion::TYPE_FIXED_COMBO,
            'selection_quantity' => 2,
            'promotion_price' => '15.00',
            'starts_at' => '2026-08-10 00:00:00',
            'ends_at' => '2026-08-20 23:59:59',
            'is_active' => true,
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
                'data.is_active',
                true,
            )
            ->assertJsonPath(
                'data.status',
                'scheduled',
            );

        CarbonImmutable::setTestNow();
    }

    public function test_active_promotion_with_expired_end_date_is_finished(): void
    {
        CarbonImmutable::setTestNow(
            '2026-08-25 12:00:00',
        );

        $admin = User::factory()
            ->admin()
            ->create();

        $promotion = Promotion::query()->create([
            'promotion_name' => 'Promoción finalizada',
            'slug' => 'promocion-finalizada',
            'description' => null,
            'banner_image_url' => null,
            'promotion_type' => Promotion::TYPE_FIXED_COMBO,
            'selection_quantity' => 2,
            'promotion_price' => '15.00',
            'starts_at' => '2026-08-10 00:00:00',
            'ends_at' => '2026-08-20 23:59:59',
            'is_active' => true,
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
                'data.is_active',
                true,
            )
            ->assertJsonPath(
                'data.status',
                'finished',
            );

        CarbonImmutable::setTestNow();
    }

    public function test_admin_can_delete_unused_promotion_and_its_configuration(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [
            'category' => $category,
            'small' => $small,
        ] = $this->promotionCatalogFixture();

        $promotion = Promotion::query()->create([
            'promotion_name' => 'Promoción eliminable',
            'slug' => 'promocion-eliminable',
            'description' => null,
            'banner_image_url' => null,
            'promotion_type' => Promotion::TYPE_FIXED_COMBO,
            'selection_quantity' => 2,
            'promotion_price' => '15.00',
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
            ->deleteJson(
                "/api/v1/admin/promotions/{$promotion->id}",
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
                'Promoción eliminada correctamente.',
            );

        $this->assertDatabaseMissing(
            'promotions',
            [
                'id' => $promotion->id,
            ],
        );

        $this->assertDatabaseMissing(
            'promotion_details',
            [
                'id' => $detail->id,
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
                'id' => $small->id,
            ],
        );
    }

    public function test_admin_cannot_delete_promotion_used_in_a_cart(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        [
            'category' => $category,
            'small' => $small,
        ] = $this->promotionCatalogFixture();

        $promotion = Promotion::query()->create([
            'promotion_name' => 'Promoción en carrito',
            'slug' => 'promocion-en-carrito',
            'description' => null,
            'banner_image_url' => null,
            'promotion_type' => Promotion::TYPE_FIXED_COMBO,
            'selection_quantity' => 2,
            'promotion_price' => '15.00',
            'starts_at' => '2026-08-10 00:00:00',
            'ends_at' => '2026-08-20 23:59:59',
            'is_active' => true,
        ]);

        PromotionDetail::query()->create([
            'promotion_id' => $promotion->id,
            'category_id' => $category->id,
            'size_id' => $small->id,
            'required_quantity' => 2,
        ]);

        $activeStatus = CartStatus::query()
            ->firstOrCreate([
                'status_name' => 'active',
            ]);

        $cart = Cart::query()->create([
            'user_id' => $customer->id,
            'cart_status_id' => $activeStatus->id,
            'session_id' => null,
            'total' => '15.00',
        ]);

        $cartItem = CartItem::query()->create([
            'cart_id' => $cart->id,
            'item_type' => 'promotion',
            'pizza_id' => null,
            'pizza_id_second' => null,
            'is_half_and_half' => false,
            'promotion_id' => $promotion->id,
            'size_id' => $small->id,
            'quantity' => 1,
            'unit_price' => '15.00',
            'subtotal' => '15.00',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->deleteJson(
                "/api/v1/admin/promotions/{$promotion->id}",
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'promotion',
            ])
            ->assertJsonPath(
                'errors.promotion.0',
                'No puedes eliminar esta promoción porque está siendo utilizada en carritos.',
            );

        $this->assertDatabaseHas(
            'promotions',
            [
                'id' => $promotion->id,
            ],
        );

        $this->assertDatabaseHas(
            'promotion_details',
            [
                'promotion_id' => $promotion->id,
                'category_id' => $category->id,
                'size_id' => $small->id,
            ],
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'id' => $cartItem->id,
                'cart_id' => $cart->id,
                'promotion_id' => $promotion->id,
                'item_type' => 'promotion',
            ],
        );
    }

    public function test_admin_cannot_delete_promotion_used_in_an_historical_order(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        [
            'category' => $category,
            'small' => $small,
        ] = $this->promotionCatalogFixture();

        $promotion = Promotion::query()->create([
            'promotion_name' => 'Promoción histórica',
            'slug' => 'promocion-historica',
            'description' => null,
            'banner_image_url' => null,
            'promotion_type' => Promotion::TYPE_FIXED_COMBO,
            'selection_quantity' => 2,
            'promotion_price' => '18.00',
            'starts_at' => '2026-08-01 00:00:00',
            'ends_at' => '2026-08-10 23:59:59',
            'is_active' => false,
        ]);

        PromotionDetail::query()->create([
            'promotion_id' => $promotion->id,
            'category_id' => $category->id,
            'size_id' => $small->id,
            'required_quantity' => 2,
        ]);

        $deliveryType = DeliveryType::query()
            ->firstOrCreate([
                'delivery_type_name' => 'pickup',
            ]);

        $paymentMethod = PaymentMethod::query()
            ->firstOrCreate([
                'name' => 'cash',
            ]);

        $orderStatus = OrderStatus::query()
            ->firstOrCreate([
                'status_name' => 'delivered',
            ]);

        $order = Order::query()->create([
            'order_number' => 'CH-PROMO-HIST-001',
            'user_id' => $customer->id,
            'ordered_at' => '2026-08-05 12:00:00',
            'subtotal' => '18.00',
            'delivery_fee' => '0.00',
            'total' => '18.00',
            'delivery_type_id' => $deliveryType->id,
            'address' => null,
            'payment_method_id' => $paymentMethod->id,
            'order_status_id' => $orderStatus->id,
        ]);

        $orderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'promotion_id' => $promotion->id,
            'promotion_name' => $promotion->promotion_name,
            'pizza_id' => null,
            'pizza_name' => null,
            'pizza_id_second' => null,
            'pizza_name_second' => null,
            'size_id' => $small->id,
            'size_name' => $small->size_name,
            'category_name' => null,
            'category_name_second' => null,
            'is_half_and_half' => false,
            'quantity' => 1,
            'unit_price' => '18.00',
            'subtotal' => '18.00',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->deleteJson(
                "/api/v1/admin/promotions/{$promotion->id}",
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'promotion',
            ])
            ->assertJsonPath(
                'errors.promotion.0',
                'No puedes eliminar esta promoción porque está registrada en pedidos históricos. Desactívala en lugar de eliminarla.',
            );

        $this->assertDatabaseHas(
            'promotions',
            [
                'id' => $promotion->id,
                'is_active' => false,
            ],
        );

        $this->assertDatabaseHas(
            'promotion_details',
            [
                'promotion_id' => $promotion->id,
                'category_id' => $category->id,
                'size_id' => $small->id,
            ],
        );

        $this->assertDatabaseHas(
            'orders',
            [
                'id' => $order->id,
                'order_number' => 'CH-PROMO-HIST-001',
            ],
        );

        $this->assertDatabaseHas(
            'order_items',
            [
                'id' => $orderItem->id,
                'order_id' => $order->id,
                'promotion_id' => $promotion->id,
                'promotion_name' => 'Promoción histórica',
            ],
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
