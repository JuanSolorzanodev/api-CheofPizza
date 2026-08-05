<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\CategorySizePrice;
use App\Models\Ingredient;
use App\Models\IngredientSizePrice;
use App\Models\IngredientType;
use App\Models\Pizza;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     regular_category: Category,
 *     premium_category: Category,
 *     regular_pizza: Pizza,
 *     premium_pizza: Pizza,
 *     size: Size,
 *     extra: Ingredient
 * }
 */
function publicCartPricingCatalog(): array
{
    $regularCategory = Category::query()->create([
        'category_name' => 'Tradicionales '.fake()->uuid(),
        'description' => 'Categoría tradicional.',
    ]);

    $premiumCategory = Category::query()->create([
        'category_name' => 'Premium '.fake()->uuid(),
        'description' => 'Categoría premium.',
    ]);

    $regularPizza = Pizza::query()->create([
        'category_id' => $regularCategory->id,
        'pizza_name' => 'Americana '.fake()->uuid(),
        'description' => null,
        'image_url' => null,
        'is_visible' => true,
    ]);

    $premiumPizza = Pizza::query()->create([
        'category_id' => $premiumCategory->id,
        'pizza_name' => 'Suprema '.fake()->uuid(),
        'description' => null,
        'image_url' => null,
        'is_visible' => true,
    ]);

    $size = Size::query()->create([
        'size_name' => 'Mediana '.fake()->uuid(),
        'portion' => 8,
    ]);

    CategorySizePrice::query()->create([
        'category_id' => $regularCategory->id,
        'size_id' => $size->id,
        'price' => '10.00',
    ]);

    CategorySizePrice::query()->create([
        'category_id' => $premiumCategory->id,
        'size_id' => $size->id,
        'price' => '14.00',
    ]);

    $ingredientType = IngredientType::query()->create([
        'type_name' => 'Extras '.fake()->uuid(),
    ]);

    $extra = Ingredient::query()->create([
        'ingredient_type_id' => $ingredientType->id,
        'ingredient_name' => 'Queso extra '.fake()->uuid(),
    ]);

    IngredientSizePrice::query()->create([
        'ingredient_id' => $extra->id,
        'size_id' => $size->id,
        'extra_price' => '2.00',
    ]);

    return [
        'regular_category' => $regularCategory,
        'premium_category' => $premiumCategory,
        'regular_pizza' => $regularPizza,
        'premium_pizza' => $premiumPizza,
        'size' => $size,
        'extra' => $extra,
    ];
}

it(
    'ignores prices sent by the client and recalculates the pizza using database values',
    function (): void {
        /** @var TestCase $this */
        [
            'regular_pizza' => $pizza,
            'size' => $size,
        ] = publicCartPricingCatalog();

        $response = $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                [
                    'pizza_id' => $pizza->id,
                    'size_id' => $size->id,
                    'quantity' => 2,
                    'is_half_and_half' => false,
                    'second_pizza_id' => null,
                    'customizations' => [],

                    /*
                     * Campos maliciosos que el backend debe ignorar.
                     */
                    'unit_price' => 0.01,
                    'subtotal' => 0.02,
                    'total' => 0.02,
                    'price' => 0.01,
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.unit_price',
                10,
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                2,
            )
            ->assertJsonPath(
                'data.items.0.subtotal',
                20,
            )
            ->assertJsonPath(
                'data.total',
                20,
            );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'pizza_id' => $pizza->id,
                'size_id' => $size->id,
                'quantity' => 2,
                'unit_price' => '10.00',
                'subtotal' => '20.00',
            ],
        );
    },
);

it(
    'uses the highest category price for a half and half pizza',
    function (): void {
        /** @var TestCase $this */
        [
            'regular_pizza' => $regularPizza,
            'premium_pizza' => $premiumPizza,
            'size' => $size,
        ] = publicCartPricingCatalog();

        $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                [
                    'pizza_id' => $regularPizza->id,
                    'second_pizza_id' => $premiumPizza->id,
                    'size_id' => $size->id,
                    'quantity' => 2,
                    'is_half_and_half' => true,
                    'customizations' => [],
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.is_half_and_half',
                true,
            )
            ->assertJsonPath(
                'data.items.0.pizza.id',
                (int) $regularPizza->id,
            )
            ->assertJsonPath(
                'data.items.0.pizza_second.id',
                (int) $premiumPizza->id,
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                14,
            )
            ->assertJsonPath(
                'data.items.0.subtotal',
                28,
            )
            ->assertJsonPath(
                'data.total',
                28,
            );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'pizza_id' => $regularPizza->id,
                'pizza_id_second' => $premiumPizza->id,
                'is_half_and_half' => true,
                'quantity' => 2,
                'unit_price' => '14.00',
                'subtotal' => '28.00',
            ],
        );
    },
);

it(
    'adds the database price of a full pizza extra',
    function (): void {
        /** @var TestCase $this */
        [
            'regular_pizza' => $pizza,
            'size' => $size,
            'extra' => $extra,
        ] = publicCartPricingCatalog();

        $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                [
                    'pizza_id' => $pizza->id,
                    'size_id' => $size->id,
                    'quantity' => 2,
                    'is_half_and_half' => false,
                    'second_pizza_id' => null,
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
            )
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.unit_price',
                12,
            )
            ->assertJsonPath(
                'data.items.0.subtotal',
                24,
            )
            ->assertJsonPath(
                'data.items.0.extras.0.ingredient.id',
                (int) $extra->id,
            )
            ->assertJsonPath(
                'data.items.0.extras.0.extra_price',
                2,
            )
            ->assertJsonPath(
                'data.total',
                24,
            );

        $this->assertDatabaseHas(
            'cart_item_personalizations',
            [
                'ingredient_id' => $extra->id,
                'applies_to' => 'ALL',
                'extra_price' => '2.00',
            ],
        );
    },
);

it(
    'charges half of the extra price when it applies to only one half',
    function (): void {
        /** @var TestCase $this */
        [
            'regular_pizza' => $regularPizza,
            'premium_pizza' => $premiumPizza,
            'size' => $size,
            'extra' => $extra,
        ] = publicCartPricingCatalog();

        $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                [
                    'pizza_id' => $regularPizza->id,
                    'second_pizza_id' => $premiumPizza->id,
                    'size_id' => $size->id,
                    'quantity' => 1,
                    'is_half_and_half' => true,
                    'customizations' => [
                        [
                            'action' => 'extra',
                            'ingredient_id' => $extra->id,
                            'applies_to' => 'A',
                        ],
                    ],
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.unit_price',
                15,
            )
            ->assertJsonPath(
                'data.items.0.subtotal',
                15,
            )
            ->assertJsonPath(
                'data.items.0.extras.0.extra_price',
                1,
            )
            ->assertJsonPath(
                'data.total',
                15,
            );

        $this->assertDatabaseHas(
            'cart_item_personalizations',
            [
                'ingredient_id' => $extra->id,
                'applies_to' => 'A',
                'extra_price' => '1.00',
            ],
        );
    },
);

it(
    'consolidates equivalent pizza configurations and recalculates the subtotal',
    function (): void {
        /** @var TestCase $this */
        [
            'regular_pizza' => $pizza,
            'size' => $size,
            'extra' => $extra,
        ] = publicCartPricingCatalog();

        $firstResponse = $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                [
                    'pizza_id' => $pizza->id,
                    'size_id' => $size->id,
                    'quantity' => 2,
                    'is_half_and_half' => false,
                    'second_pizza_id' => null,
                    'customizations' => [
                        [
                            'action' => 'extra',
                            'ingredient_id' => $extra->id,
                            'applies_to' => 'ALL',
                        ],
                    ],
                ],
            )
            ->assertOk();

        $sessionId = (string) $firstResponse
            ->headers
            ->get('X-Cart-Session');

        $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                [
                    'pizza_id' => $pizza->id,
                    'size_id' => $size->id,
                    'quantity' => 3,
                    'is_half_and_half' => false,
                    'second_pizza_id' => null,
                    'customizations' => [
                        [
                            'action' => 'extra',
                            'ingredient_id' => $extra->id,
                            'applies_to' => 'ALL',
                        ],
                    ],
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
                12,
            )
            ->assertJsonPath(
                'data.items.0.subtotal',
                60,
            )
            ->assertJsonPath(
                'data.total',
                60,
            );

        $this->assertDatabaseCount(
            'cart_items',
            1,
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'pizza_id' => $pizza->id,
                'size_id' => $size->id,
                'quantity' => 5,
                'unit_price' => '12.00',
                'subtotal' => '60.00',
            ],
        );

        $this->assertDatabaseCount(
            'cart_item_personalizations',
            1,
        );
    },
);

it(
    'rejects an accumulated equivalent quantity greater than ten',
    function (): void {
        /** @var TestCase $this */
        [
            'regular_pizza' => $pizza,
            'size' => $size,
        ] = publicCartPricingCatalog();

        $firstResponse = $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                [
                    'pizza_id' => $pizza->id,
                    'size_id' => $size->id,
                    'quantity' => 8,
                    'is_half_and_half' => false,
                    'second_pizza_id' => null,
                    'customizations' => [],
                ],
            )
            ->assertOk();

        $sessionId = (string) $firstResponse
            ->headers
            ->get('X-Cart-Session');

        $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                [
                    'pizza_id' => $pizza->id,
                    'size_id' => $size->id,
                    'quantity' => 3,
                    'is_half_and_half' => false,
                    'second_pizza_id' => null,
                    'customizations' => [],
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
                'La cantidad total para esta configuración no puede superar 10 pizzas.',
            );

        $this->assertDatabaseCount(
            'cart_items',
            1,
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'pizza_id' => $pizza->id,
                'quantity' => 8,
                'unit_price' => '10.00',
                'subtotal' => '80.00',
            ],
        );
    },
);

it(
    'rejects an invisible pizza without creating a cart item',
    function (): void {
        /** @var TestCase $this */
        [
            'regular_pizza' => $pizza,
            'size' => $size,
        ] = publicCartPricingCatalog();

        $pizza
            ->forceFill([
                'is_visible' => false,
            ])
            ->save();

        $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                [
                    'pizza_id' => $pizza->id,
                    'size_id' => $size->id,
                    'quantity' => 1,
                    'is_half_and_half' => false,
                    'second_pizza_id' => null,
                    'customizations' => [],
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'pizza_id',
            ])
            ->assertJsonPath(
                'errors.pizza_id.0',
                'La pizza seleccionada no existe o no está disponible.',
            );

        $this->assertDatabaseCount(
            'cart_items',
            0,
        );
    },
);
