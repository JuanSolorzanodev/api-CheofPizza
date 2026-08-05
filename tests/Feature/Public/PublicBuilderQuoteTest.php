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
 *     unavailable_pizza: Pizza,
 *     size: Size,
 *     unavailable_size: Size,
 *     cheese: Ingredient,
 *     ham: Ingredient,
 *     extra: Ingredient
 * }
 */
function publicBuilderQuoteCatalog(): array
{
    $regularCategory = Category::query()->create([
        'category_name' => 'Tradicional constructor '.fake()->uuid(),
        'description' => 'Categoría tradicional.',
    ]);

    $premiumCategory = Category::query()->create([
        'category_name' => 'Premium constructor '.fake()->uuid(),
        'description' => 'Categoría premium.',
    ]);

    $regularPizza = Pizza::query()->create([
        'category_id' => $regularCategory->id,
        'pizza_name' => 'Americana constructor '.fake()->uuid(),
        'description' => 'Pizza tradicional.',
        'image_url' => null,
        'is_visible' => true,
    ]);

    $premiumPizza = Pizza::query()->create([
        'category_id' => $premiumCategory->id,
        'pizza_name' => 'Suprema constructor '.fake()->uuid(),
        'description' => 'Pizza premium.',
        'image_url' => null,
        'is_visible' => true,
    ]);

    $unavailablePizza = Pizza::query()->create([
        'category_id' => $regularCategory->id,
        'pizza_name' => 'Pizza oculta constructor '.fake()->uuid(),
        'description' => null,
        'image_url' => null,
        'is_visible' => false,
    ]);

    $size = Size::query()->create([
        'size_name' => 'Mediana constructor '.fake()->uuid(),
        'portion' => 8,
    ]);

    $unavailableSize = Size::query()->create([
        'size_name' => 'Gigante no disponible '.fake()->uuid(),
        'portion' => 12,
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
        'type_name' => 'Ingredientes constructor '.fake()->uuid(),
    ]);

    $cheese = Ingredient::query()->create([
        'ingredient_type_id' => $ingredientType->id,
        'ingredient_name' => 'Queso constructor '.fake()->uuid(),
    ]);

    $ham = Ingredient::query()->create([
        'ingredient_type_id' => $ingredientType->id,
        'ingredient_name' => 'Jamón constructor '.fake()->uuid(),
    ]);

    $extra = Ingredient::query()->create([
        'ingredient_type_id' => $ingredientType->id,
        'ingredient_name' => 'Tocino extra constructor '.fake()->uuid(),
    ]);

    IngredientSizePrice::query()->create([
        'ingredient_id' => $cheese->id,
        'size_id' => $size->id,
        'extra_price' => '1.50',
    ]);

    IngredientSizePrice::query()->create([
        'ingredient_id' => $ham->id,
        'size_id' => $size->id,
        'extra_price' => '2.00',
    ]);

    IngredientSizePrice::query()->create([
        'ingredient_id' => $extra->id,
        'size_id' => $size->id,
        'extra_price' => '3.00',
    ]);

    $regularPizza->ingredients()->attach([
        $cheese->id,
        $ham->id,
    ]);

    $premiumPizza->ingredients()->attach([
        $cheese->id,
        $extra->id,
    ]);

    return [
        'regular_category' => $regularCategory,
        'premium_category' => $premiumCategory,
        'regular_pizza' => $regularPizza,
        'premium_pizza' => $premiumPizza,
        'unavailable_pizza' => $unavailablePizza,
        'size' => $size,
        'unavailable_size' => $unavailableSize,
        'cheese' => $cheese,
        'ham' => $ham,
        'extra' => $extra,
    ];
}

it(
    'quotes a complete pizza using only database prices',
    function (): void {
        /** @var TestCase $this */
        [
            'regular_pizza' => $pizza,
            'size' => $size,
        ] = publicBuilderQuoteCatalog();

        $this
            ->postJson(
                '/api/v1/public/builder/quote',
                [
                    'pizza_id' => $pizza->id,
                    'size_id' => $size->id,
                    'quantity' => 2,
                    'is_half_and_half' => false,
                    'second_pizza_id' => null,
                    'customizations' => [],

                    /*
                     * Valores maliciosos que deben ignorarse.
                     */
                    'base_price' => 0.01,
                    'unit_price' => 0.01,
                    'total' => 0.02,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.pizza_a.id',
                (int) $pizza->id,
            )
            ->assertJsonPath(
                'data.pizza_b',
                null,
            )
            ->assertJsonPath(
                'data.size_id',
                (int) $size->id,
            )
            ->assertJsonPath(
                'data.quantity',
                2,
            )
            ->assertJsonPath(
                'data.base_price_a',
                10,
            )
            ->assertJsonPath(
                'data.base_price_b',
                0,
            )
            ->assertJsonPath(
                'data.base_price',
                10,
            )
            ->assertJsonPath(
                'data.extras_total',
                0,
            )
            ->assertJsonPath(
                'data.unit_price',
                10,
            )
            ->assertJsonPath(
                'data.total',
                20,
            )
            ->assertJsonPath(
                'data.extras_breakdown',
                [],
            )
            ->assertJsonPath(
                'data.removes_breakdown',
                [],
            );
    },
);

it(
    'uses the highest base price for a half and half pizza',
    function (): void {
        /** @var TestCase $this */
        [
            'regular_pizza' => $regularPizza,
            'premium_pizza' => $premiumPizza,
            'size' => $size,
        ] = publicBuilderQuoteCatalog();

        $this
            ->postJson(
                '/api/v1/public/builder/quote',
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
                'data.pizza_a.id',
                (int) $regularPizza->id,
            )
            ->assertJsonPath(
                'data.pizza_b.id',
                (int) $premiumPizza->id,
            )
            ->assertJsonPath(
                'data.base_price_a',
                10,
            )
            ->assertJsonPath(
                'data.base_price_b',
                14,
            )
            ->assertJsonPath(
                'data.base_price',
                14,
            )
            ->assertJsonPath(
                'data.unit_price',
                14,
            )
            ->assertJsonPath(
                'data.total',
                28,
            );
    },
);

it(
    'calculates complete and half extras using database prices',
    function (): void {
        /** @var TestCase $this */
        [
            'regular_pizza' => $regularPizza,
            'premium_pizza' => $premiumPizza,
            'size' => $size,
            'ham' => $ham,
            'extra' => $extra,
        ] = publicBuilderQuoteCatalog();

        $this
            ->postJson(
                '/api/v1/public/builder/quote',
                [
                    'pizza_id' => $regularPizza->id,
                    'second_pizza_id' => $premiumPizza->id,
                    'size_id' => $size->id,
                    'quantity' => 2,
                    'is_half_and_half' => true,

                    'customizations' => [
                        [
                            'action' => 'extra',
                            'ingredient_id' => $ham->id,
                            'applies_to' => 'ALL',
                            'extra_price' => 0.01,
                        ],
                        [
                            'action' => 'extra',
                            'ingredient_id' => $extra->id,
                            'applies_to' => 'B',
                            'extra_price' => 0.01,
                        ],
                    ],
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.base_price',
                14,
            )
            ->assertJsonPath(
                'data.extras_total',
                3.5,
            )
            ->assertJsonPath(
                'data.unit_price',
                17.5,
            )
            ->assertJsonPath(
                'data.total',
                35,
            )
            ->assertJsonPath(
                'data.extras_breakdown.0.ingredient_id',
                (int) $ham->id,
            )
            ->assertJsonPath(
                'data.extras_breakdown.0.unit_extra_price',
                2,
            )
            ->assertJsonPath(
                'data.extras_breakdown.0.multiplier',
                1,
            )
            ->assertJsonPath(
                'data.extras_breakdown.0.line_total',
                2,
            )
            ->assertJsonPath(
                'data.extras_breakdown.1.ingredient_id',
                (int) $extra->id,
            )
            ->assertJsonPath(
                'data.extras_breakdown.1.unit_extra_price',
                3,
            )
            ->assertJsonPath(
                'data.extras_breakdown.1.multiplier',
                0.5,
            )
            ->assertJsonPath(
                'data.extras_breakdown.1.line_total',
                1.5,
            );
    },
);

it(
    'records removed ingredients without reducing the price',
    function (): void {
        /** @var TestCase $this */
        [
            'regular_pizza' => $pizza,
            'size' => $size,
            'ham' => $ham,
        ] = publicBuilderQuoteCatalog();

        $this
            ->postJson(
                '/api/v1/public/builder/quote',
                [
                    'pizza_id' => $pizza->id,
                    'size_id' => $size->id,
                    'quantity' => 1,
                    'is_half_and_half' => false,

                    'customizations' => [
                        [
                            'action' => 'remove',
                            'ingredient_id' => $ham->id,
                            'applies_to' => 'ALL',
                        ],
                    ],
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.base_price',
                10,
            )
            ->assertJsonPath(
                'data.extras_total',
                0,
            )
            ->assertJsonPath(
                'data.unit_price',
                10,
            )
            ->assertJsonPath(
                'data.total',
                10,
            )
            ->assertJsonCount(
                0,
                'data.extras_breakdown',
            )
            ->assertJsonCount(
                1,
                'data.removes_breakdown',
            )
            ->assertJsonPath(
                'data.removes_breakdown.0.action',
                'remove',
            )
            ->assertJsonPath(
                'data.removes_breakdown.0.ingredient_id',
                (int) $ham->id,
            )
            ->assertJsonPath(
                'data.removes_breakdown.0.applies_to',
                'ALL',
            )
            ->assertJsonPath(
                'data.removes_breakdown.0.line_total',
                0,
            );
    },
);

it(
    'supports the legacy extras payload',
    function (): void {
        /** @var TestCase $this */
        [
            'regular_pizza' => $pizza,
            'size' => $size,
            'extra' => $extra,
        ] = publicBuilderQuoteCatalog();

        $this
            ->postJson(
                '/api/v1/public/builder/quote',
                [
                    'pizza_id' => $pizza->id,
                    'size_id' => $size->id,
                    'quantity' => 2,
                    'is_half_and_half' => false,

                    'extras' => [
                        [
                            'ingredient_id' => $extra->id,
                            'applies_to' => 'ALL',
                        ],
                    ],
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.base_price',
                10,
            )
            ->assertJsonPath(
                'data.extras_total',
                3,
            )
            ->assertJsonPath(
                'data.unit_price',
                13,
            )
            ->assertJsonPath(
                'data.total',
                26,
            )
            ->assertJsonPath(
                'data.extras_breakdown.0.action',
                'extra',
            )
            ->assertJsonPath(
                'data.extras_breakdown.0.ingredient_id',
                (int) $extra->id,
            )
            ->assertJsonPath(
                'data.extras_breakdown.0.applies_to',
                'ALL',
            );
    },
);

it(
    'rejects hidden pizzas and unavailable sizes',
    function (): void {
        /** @var TestCase $this */
        [
            'regular_pizza' => $regularPizza,
            'unavailable_pizza' => $hiddenPizza,
            'size' => $size,
            'unavailable_size' => $unavailableSize,
        ] = publicBuilderQuoteCatalog();

        $this
            ->postJson(
                '/api/v1/public/builder/quote',
                [
                    'pizza_id' => $hiddenPizza->id,
                    'size_id' => $size->id,
                    'quantity' => 1,
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

        $this
            ->postJson(
                '/api/v1/public/builder/quote',
                [
                    'pizza_id' => $regularPizza->id,
                    'size_id' => $unavailableSize->id,
                    'quantity' => 1,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'size_id',
            ]);

        $errors = $this
            ->postJson(
                '/api/v1/public/builder/quote',
                [
                    'pizza_id' => $regularPizza->id,
                    'size_id' => $unavailableSize->id,
                    'quantity' => 1,
                ],
            )
            ->assertUnprocessable()
            ->json('errors.size_id');

        expect($errors)
            ->toBeArray()
            ->not
            ->toBeEmpty();
    },
);

it(
    'validates the second flavor and quantity limits',
    function (): void {
        /** @var TestCase $this */
        [
            'regular_pizza' => $pizza,
            'size' => $size,
        ] = publicBuilderQuoteCatalog();

        $this
            ->postJson(
                '/api/v1/public/builder/quote',
                [
                    'pizza_id' => $pizza->id,
                    'size_id' => $size->id,
                    'quantity' => 1,
                    'is_half_and_half' => true,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'second_pizza_id',
            ])
            ->assertJsonPath(
                'errors.second_pizza_id.0',
                'Debes seleccionar el segundo sabor para mitad y mitad.',
            );

        $this
            ->postJson(
                '/api/v1/public/builder/quote',
                [
                    'pizza_id' => $pizza->id,
                    'second_pizza_id' => $pizza->id,
                    'size_id' => $size->id,
                    'quantity' => 1,
                    'is_half_and_half' => true,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'second_pizza_id',
            ])
            ->assertJsonPath(
                'errors.second_pizza_id.0',
                'El segundo sabor debe ser diferente al primero.',
            );

        $this
            ->postJson(
                '/api/v1/public/builder/quote',
                [
                    'pizza_id' => $pizza->id,
                    'size_id' => $size->id,
                    'quantity' => 11,
                    'is_half_and_half' => false,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'quantity',
            ]);
    },
);

it(
    'rejects invalid customization rules',
    function (): void {
        /** @var TestCase $this */
        [
            'regular_pizza' => $pizza,
            'size' => $size,
            'ham' => $ham,
            'extra' => $extra,
        ] = publicBuilderQuoteCatalog();

        /*
         * En una pizza completa, las personalizaciones solo
         * pueden aplicarse a toda la pizza.
         */
        $invalidSideResponse = $this
            ->postJson(
                '/api/v1/public/builder/quote',
                [
                    'pizza_id' => $pizza->id,
                    'size_id' => $size->id,
                    'quantity' => 1,
                    'is_half_and_half' => false,

                    'customizations' => [
                        [
                            'action' => 'extra',
                            'ingredient_id' => $extra->id,
                            'applies_to' => 'A',
                        ],
                    ],
                ],
            );

        $invalidSideResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customizations.0.applies_to',
            ]);

        $invalidSideErrors = $invalidSideResponse->json(
            'errors',
        );

        expect($invalidSideErrors)
            ->toBeArray();

        expect(
            $invalidSideErrors['customizations.0.applies_to'][0] ?? null,
        )->toBe(
            'En una pizza completa la personalización debe aplicarse a ALL.',
        );

        /*
         * No se puede quitar un ingrediente que no pertenece
         * originalmente a la pizza.
         */
        $invalidRemovalResponse = $this
            ->postJson(
                '/api/v1/public/builder/quote',
                [
                    'pizza_id' => $pizza->id,
                    'size_id' => $size->id,
                    'quantity' => 1,
                    'is_half_and_half' => false,

                    'customizations' => [
                        [
                            'action' => 'remove',
                            'ingredient_id' => $extra->id,
                            'applies_to' => 'ALL',
                        ],
                    ],
                ],
            );

        $invalidRemovalResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customizations.0.ingredient_id',
            ]);

        $invalidRemovalErrors = $invalidRemovalResponse->json(
            'errors',
        );

        expect($invalidRemovalErrors)
            ->toBeArray();

        expect(
            $invalidRemovalErrors['customizations.0.ingredient_id'][0] ?? null,
        )->toBe(
            'No puedes quitar un ingrediente que no pertenece a la pizza.',
        );

        /*
         * No se permiten personalizaciones repetidas para la misma
         * acción, ingrediente y sección de la pizza.
         */
        $duplicateResponse = $this
            ->postJson(
                '/api/v1/public/builder/quote',
                [
                    'pizza_id' => $pizza->id,
                    'size_id' => $size->id,
                    'quantity' => 1,
                    'is_half_and_half' => false,

                    'customizations' => [
                        [
                            'action' => 'remove',
                            'ingredient_id' => $ham->id,
                            'applies_to' => 'ALL',
                        ],
                        [
                            'action' => 'remove',
                            'ingredient_id' => $ham->id,
                            'applies_to' => 'ALL',
                        ],
                    ],
                ],
            );

        $duplicateResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customizations',
            ]);

        $duplicateErrors = $duplicateResponse->json(
            'errors',
        );

        expect($duplicateErrors)
            ->toBeArray();

        expect(
            $duplicateErrors['customizations'][0]
                ?? null,
        )->toBe(
            'No se permiten personalizaciones duplicadas.',
        );
    },
);
