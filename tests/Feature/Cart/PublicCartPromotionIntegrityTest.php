<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientSizePrice;
use App\Models\IngredientType;
use App\Models\Pizza;
use App\Models\Promotion;
use App\Models\PromotionDetail;
use App\Models\PromotionSizePrice;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     traditional_category: Category,
 *     premium_category: Category,
 *     traditional_pizza: Pizza,
 *     second_traditional_pizza: Pizza,
 *     premium_pizza: Pizza,
 *     small: Size,
 *     medium: Size,
 *     extra: Ingredient,
 *     fixed_combo: Promotion,
 *     size_promotion: Promotion
 * }
 */
function publicCartPromotionCatalog(): array
{
    $traditionalCategory = Category::query()->create([
        'category_name' => 'Tradicionales promoción '.fake()->uuid(),
        'description' => null,
    ]);

    $premiumCategory = Category::query()->create([
        'category_name' => 'Premium promoción '.fake()->uuid(),
        'description' => null,
    ]);

    $traditionalPizza = Pizza::query()->create([
        'category_id' => $traditionalCategory->id,
        'pizza_name' => 'Americana promoción '.fake()->uuid(),
        'description' => null,
        'image_url' => null,
        'is_visible' => true,
    ]);

    $secondTraditionalPizza = Pizza::query()->create([
        'category_id' => $traditionalCategory->id,
        'pizza_name' => 'Hawaiana promoción '.fake()->uuid(),
        'description' => null,
        'image_url' => null,
        'is_visible' => true,
    ]);

    $premiumPizza = Pizza::query()->create([
        'category_id' => $premiumCategory->id,
        'pizza_name' => 'Suprema promoción '.fake()->uuid(),
        'description' => null,
        'image_url' => null,
        'is_visible' => true,
    ]);

    $small = Size::query()->create([
        'size_name' => 'Pequeña promoción '.fake()->uuid(),
        'portion' => 4,
    ]);

    $medium = Size::query()->create([
        'size_name' => 'Mediana promoción '.fake()->uuid(),
        'portion' => 8,
    ]);

    $ingredientType = IngredientType::query()->create([
        'type_name' => 'Extras promoción '.fake()->uuid(),
    ]);

    $extra = Ingredient::query()->create([
        'ingredient_type_id' => $ingredientType->id,
        'ingredient_name' => 'Queso extra promoción '.fake()->uuid(),
    ]);

    IngredientSizePrice::query()->create([
        'ingredient_id' => $extra->id,
        'size_id' => $small->id,
        'extra_price' => '1.50',
    ]);

    IngredientSizePrice::query()->create([
        'ingredient_id' => $extra->id,
        'size_id' => $medium->id,
        'extra_price' => '2.00',
    ]);

    $fixedCombo = Promotion::query()->create([
        'promotion_name' => 'Combo tradicional',
        'slug' => 'combo-tradicional-'.fake()->uuid(),
        'description' => null,
        'banner_image_url' => null,
        'promotion_type' => Promotion::TYPE_FIXED_COMBO,
        'selection_quantity' => 2,
        'promotion_price' => '15.00',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
    ]);

    PromotionDetail::query()->create([
        'promotion_id' => $fixedCombo->id,
        'category_id' => $traditionalCategory->id,
        'size_id' => $small->id,
        'required_quantity' => 2,
    ]);

    $sizePromotion = Promotion::query()->create([
        'promotion_name' => 'Precio fijo por tamaño',
        'slug' => 'precio-fijo-'.fake()->uuid(),
        'description' => null,
        'banner_image_url' => null,
        'promotion_type' => Promotion::TYPE_SIZE_FIXED_PRICE,
        'selection_quantity' => 1,
        'promotion_price' => '0.00',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
    ]);

    PromotionSizePrice::query()->create([
        'promotion_id' => $sizePromotion->id,
        'size_id' => $small->id,
        'fixed_price' => '6.50',
    ]);

    PromotionSizePrice::query()->create([
        'promotion_id' => $sizePromotion->id,
        'size_id' => $medium->id,
        'fixed_price' => '9.75',
    ]);

    return [
        'traditional_category' => $traditionalCategory,
        'premium_category' => $premiumCategory,
        'traditional_pizza' => $traditionalPizza,
        'second_traditional_pizza' => $secondTraditionalPizza,
        'premium_pizza' => $premiumPizza,
        'small' => $small,
        'medium' => $medium,
        'extra' => $extra,
        'fixed_combo' => $fixedCombo,
        'size_promotion' => $sizePromotion,
    ];
}

it(
    'uses the database price for a fixed combo',
    function (): void {
        /** @var TestCase $this */
        [
            'traditional_pizza' => $firstPizza,
            'second_traditional_pizza' => $secondPizza,
            'fixed_combo' => $promotion,
            'small' => $small,
        ] = publicCartPromotionCatalog();

        $this
            ->postJson(
                '/api/v1/public/cart/items/promotion',
                [
                    'promotion_id' => $promotion->id,
                    'quantity' => 2,

                    'selected_items' => [
                        [
                            'pizza_id' => $firstPizza->id,
                            'customizations' => [],
                        ],
                        [
                            'pizza_id' => $secondPizza->id,
                            'customizations' => [],
                        ],
                    ],

                    /*
                     * Campos enviados maliciosamente.
                     */
                    'price' => 0.01,
                    'unit_price' => 0.01,
                    'subtotal' => 0.02,
                    'total' => 0.02,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.item_type',
                'promotion',
            )
            ->assertJsonPath(
                'data.items.0.promotion.id',
                (int) $promotion->id,
            )
            ->assertJsonPath(
                'data.items.0.size.id',
                (int) $small->id,
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                2,
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                15,
            )
            ->assertJsonPath(
                'data.items.0.subtotal',
                30,
            )
            ->assertJsonPath(
                'data.total',
                30,
            );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'promotion_id' => $promotion->id,
                'size_id' => $small->id,
                'quantity' => 2,
                'unit_price' => '15.00',
                'subtotal' => '30.00',
            ],
        );

        $this->assertDatabaseCount(
            'cart_promotion_items',
            2,
        );
    },
);

it(
    'uses the configured fixed price for the selected promotion size',
    function (): void {
        /** @var TestCase $this */
        [
            'traditional_pizza' => $pizza,
            'size_promotion' => $promotion,
            'medium' => $medium,
        ] = publicCartPromotionCatalog();

        $this
            ->postJson(
                '/api/v1/public/cart/items/promotion',
                [
                    'promotion_id' => $promotion->id,
                    'size_id' => $medium->id,
                    'quantity' => 3,

                    'selected_items' => [
                        [
                            'pizza_id' => $pizza->id,
                            'customizations' => [],
                        ],
                    ],
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.size.id',
                (int) $medium->id,
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                9.75,
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                3,
            )
            ->assertJsonPath(
                'data.items.0.subtotal',
                29.25,
            )
            ->assertJsonPath(
                'data.total',
                29.25,
            );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'promotion_id' => $promotion->id,
                'size_id' => $medium->id,
                'quantity' => 3,
                'unit_price' => '9.75',
                'subtotal' => '29.25',
            ],
        );
    },
);

it(
    'adds database extras to the promotion package price',
    function (): void {
        /** @var TestCase $this */
        [
            'traditional_pizza' => $firstPizza,
            'second_traditional_pizza' => $secondPizza,
            'fixed_combo' => $promotion,
            'extra' => $extra,
        ] = publicCartPromotionCatalog();

        $this
            ->postJson(
                '/api/v1/public/cart/items/promotion',
                [
                    'promotion_id' => $promotion->id,
                    'quantity' => 2,

                    'selected_items' => [
                        [
                            'pizza_id' => $firstPizza->id,

                            'customizations' => [
                                [
                                    'action' => 'extra',
                                    'ingredient_id' => $extra->id,
                                    'applies_to' => 'ALL',

                                    /*
                                     * Debe ser ignorado.
                                     */
                                    'extra_price' => 0.01,
                                ],
                            ],
                        ],

                        [
                            'pizza_id' => $secondPizza->id,
                            'customizations' => [],
                        ],
                    ],
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.unit_price',
                16.5,
            )
            ->assertJsonPath(
                'data.items.0.subtotal',
                33,
            )
            ->assertJsonPath(
                'data.items.0.selected_pizzas.0.customizations.0.extra_price',
                1.5,
            )
            ->assertJsonPath(
                'data.total',
                33,
            );

        $this->assertDatabaseHas(
            'cart_item_personalizations',
            [
                'ingredient_id' => $extra->id,
                'applies_to' => 'ALL',
                'extra_price' => '1.50',
            ],
        );
    },
);

it(
    'rejects an invalid number of selected pizzas',
    function (): void {
        /** @var TestCase $this */
        [
            'traditional_pizza' => $pizza,
            'fixed_combo' => $promotion,
        ] = publicCartPromotionCatalog();

        $this
            ->postJson(
                '/api/v1/public/cart/items/promotion',
                [
                    'promotion_id' => $promotion->id,
                    'quantity' => 1,

                    'selected_items' => [
                        [
                            'pizza_id' => $pizza->id,
                            'customizations' => [],
                        ],
                    ],
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'selected_items',
            ])
            ->assertJsonPath(
                'errors.selected_items.0',
                'Debes seleccionar exactamente 2 pizzas para esta promoción.',
            );

        $this->assertDatabaseCount(
            'cart_items',
            0,
        );
    },
);

it(
    'rejects pizzas that do not match the categories required by the combo',
    function (): void {
        /** @var TestCase $this */
        [
            'traditional_pizza' => $traditionalPizza,
            'premium_pizza' => $premiumPizza,
            'fixed_combo' => $promotion,
            'traditional_category' => $category,
        ] = publicCartPromotionCatalog();

        $response = $this
            ->postJson(
                '/api/v1/public/cart/items/promotion',
                [
                    'promotion_id' => $promotion->id,
                    'quantity' => 1,

                    'selected_items' => [
                        [
                            'pizza_id' => $traditionalPizza->id,
                            'customizations' => [],
                        ],
                        [
                            'pizza_id' => $premiumPizza->id,
                            'customizations' => [],
                        ],
                    ],
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'selected_items',
            ]);

        $errors = $response->json('errors');

        expect(
            $errors['selected_items'] ?? [],
        )->toContain(
            "La promoción requiere 2 pizza(s) de la categoría {$category->category_name}.",
        );

        $this->assertDatabaseCount(
            'cart_items',
            0,
        );
    },
);

it(
    'requires a configured size for fixed price promotions',
    function (): void {
        /** @var TestCase $this */
        [
            'traditional_pizza' => $pizza,
            'size_promotion' => $promotion,
        ] = publicCartPromotionCatalog();

        $this
            ->postJson(
                '/api/v1/public/cart/items/promotion',
                [
                    'promotion_id' => $promotion->id,
                    'quantity' => 1,

                    'selected_items' => [
                        [
                            'pizza_id' => $pizza->id,
                            'customizations' => [],
                        ],
                    ],
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'size_id',
            ])
            ->assertJsonPath(
                'errors.size_id.0',
                'Debes seleccionar el tamaño de la promoción.',
            );

        $this->assertDatabaseCount(
            'cart_items',
            0,
        );
    },
);

it(
    'consolidates equivalent promotion configurations',
    function (): void {
        /** @var TestCase $this */
        [
            'traditional_pizza' => $firstPizza,
            'second_traditional_pizza' => $secondPizza,
            'fixed_combo' => $promotion,
        ] = publicCartPromotionCatalog();

        $payload = [
            'promotion_id' => $promotion->id,

            'selected_items' => [
                [
                    'pizza_id' => $firstPizza->id,
                    'customizations' => [],
                ],
                [
                    'pizza_id' => $secondPizza->id,
                    'customizations' => [],
                ],
            ],
        ];

        $firstResponse = $this
            ->postJson(
                '/api/v1/public/cart/items/promotion',
                [
                    ...$payload,
                    'quantity' => 2,
                ],
            )
            ->assertOk();

        $sessionId = (string) $firstResponse
            ->headers
            ->get('X-Cart-Session');

        $this
            ->postJson(
                '/api/v1/public/cart/items/promotion',
                [
                    ...$payload,
                    'quantity' => 3,
                ],
                [
                    'X-Cart-Session' => $sessionId,
                ],
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.items',
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                5,
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                15,
            )
            ->assertJsonPath(
                'data.items.0.subtotal',
                75,
            )
            ->assertJsonPath(
                'data.total',
                75,
            );

        $this->assertDatabaseCount(
            'cart_items',
            1,
        );

        $this->assertDatabaseCount(
            'cart_promotion_items',
            2,
        );
    },
);

it(
    'rejects an accumulated promotion quantity greater than ten',
    function (): void {
        /** @var TestCase $this */
        [
            'traditional_pizza' => $firstPizza,
            'second_traditional_pizza' => $secondPizza,
            'fixed_combo' => $promotion,
        ] = publicCartPromotionCatalog();

        $payload = [
            'promotion_id' => $promotion->id,

            'selected_items' => [
                [
                    'pizza_id' => $firstPizza->id,
                    'customizations' => [],
                ],
                [
                    'pizza_id' => $secondPizza->id,
                    'customizations' => [],
                ],
            ],
        ];

        $firstResponse = $this
            ->postJson(
                '/api/v1/public/cart/items/promotion',
                [
                    ...$payload,
                    'quantity' => 8,
                ],
            )
            ->assertOk();

        $sessionId = (string) $firstResponse
            ->headers
            ->get('X-Cart-Session');

        $this
            ->postJson(
                '/api/v1/public/cart/items/promotion',
                [
                    ...$payload,
                    'quantity' => 3,
                ],
                [
                    'X-Cart-Session' => $sessionId,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'quantity',
            ])
            ->assertJsonPath(
                'errors.quantity.0',
                'La cantidad total de esta promoción no puede superar 10.',
            );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'promotion_id' => $promotion->id,
                'quantity' => 8,
                'unit_price' => '15.00',
                'subtotal' => '120.00',
            ],
        );
    },
);

it(
    'rejects inactive and expired promotions',
    function (): void {
        /** @var TestCase $this */
        [
            'traditional_pizza' => $firstPizza,
            'second_traditional_pizza' => $secondPizza,
            'fixed_combo' => $promotion,
        ] = publicCartPromotionCatalog();

        $payload = [
            'promotion_id' => $promotion->id,
            'quantity' => 1,

            'selected_items' => [
                [
                    'pizza_id' => $firstPizza->id,
                    'customizations' => [],
                ],
                [
                    'pizza_id' => $secondPizza->id,
                    'customizations' => [],
                ],
            ],
        ];

        $promotion
            ->forceFill([
                'is_active' => false,
            ])
            ->save();

        $this
            ->postJson(
                '/api/v1/public/cart/items/promotion',
                $payload,
            )
            ->assertNotFound();

        $promotion
            ->forceFill([
                'is_active' => true,
                'starts_at' => now()->subDays(3),
                'ends_at' => now()->subDay(),
            ])
            ->save();

        $this
            ->postJson(
                '/api/v1/public/cart/items/promotion',
                $payload,
            )
            ->assertNotFound();

        $this->assertDatabaseCount(
            'cart_items',
            0,
        );
    },
);

it(
    'supports the legacy selected pizza ids payload',
    function (): void {
        /** @var TestCase $this */
        [
            'traditional_pizza' => $firstPizza,
            'second_traditional_pizza' => $secondPizza,
            'fixed_combo' => $promotion,
        ] = publicCartPromotionCatalog();

        $this
            ->postJson(
                '/api/v1/public/cart/items/promotion',
                [
                    'promotion_id' => $promotion->id,
                    'quantity' => 1,

                    'selected_pizza_ids' => [
                        $firstPizza->id,
                        $secondPizza->id,
                    ],
                ],
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.items',
            )
            ->assertJsonCount(
                2,
                'data.items.0.selected_pizzas',
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                15,
            )
            ->assertJsonPath(
                'data.items.0.subtotal',
                15,
            )
            ->assertJsonPath(
                'data.total',
                15,
            );

        $this->assertDatabaseCount(
            'cart_promotion_items',
            2,
        );
    },
);
