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
 *     sencillas: Category,
 *     especiales: Category,
 *     without_price: Category,
 *     small: Size,
 *     medium: Size,
 *     unavailable_size: Size,
 *     sencilla: Pizza,
 *     especial: Pizza,
 *     hidden: Pizza,
 *     unavailable: Pizza,
 *     cheese: Ingredient,
 *     ham: Ingredient
 * }
 */
function publicCatalogData(): array
{
    $sencillas = Category::query()->create([
        'category_name' => 'Sencillas',
        'description' => 'Pizzas sencillas.',
    ]);

    $especiales = Category::query()->create([
        'category_name' => 'Especiales',
        'description' => 'Pizzas especiales.',
    ]);

    $withoutPrice = Category::query()->create([
        'category_name' => 'Sin precios',
        'description' => 'Categoría sin precios comerciales.',
    ]);

    $small = Size::query()->create([
        'size_name' => 'Pequeña',
        'portion' => 4,
    ]);

    $medium = Size::query()->create([
        'size_name' => 'Mediana',
        'portion' => 8,
    ]);

    $unavailableSize = Size::query()->create([
        'size_name' => 'Gigante',
        'portion' => 12,
    ]);

    CategorySizePrice::query()->create([
        'category_id' => $sencillas->id,
        'size_id' => $small->id,
        'price' => '8.50',
    ]);

    CategorySizePrice::query()->create([
        'category_id' => $sencillas->id,
        'size_id' => $medium->id,
        'price' => '12.00',
    ]);

    CategorySizePrice::query()->create([
        'category_id' => $sencillas->id,
        'size_id' => $unavailableSize->id,
        'price' => '0.00',
    ]);

    CategorySizePrice::query()->create([
        'category_id' => $especiales->id,
        'size_id' => $medium->id,
        'price' => '15.00',
    ]);

    $ingredientType = IngredientType::query()->create([
        'type_name' => 'Ingredientes base',
    ]);

    $cheese = Ingredient::query()->create([
        'ingredient_type_id' => $ingredientType->id,
        'ingredient_name' => 'Queso',
    ]);

    $ham = Ingredient::query()->create([
        'ingredient_type_id' => $ingredientType->id,
        'ingredient_name' => 'Jamón',
    ]);

    IngredientSizePrice::query()->create([
        'ingredient_id' => $cheese->id,
        'size_id' => $small->id,
        'extra_price' => '1.25',
    ]);

    IngredientSizePrice::query()->create([
        'ingredient_id' => $cheese->id,
        'size_id' => $medium->id,
        'extra_price' => '2.00',
    ]);

    IngredientSizePrice::query()->create([
        'ingredient_id' => $cheese->id,
        'size_id' => $unavailableSize->id,
        'extra_price' => '0.00',
    ]);

    IngredientSizePrice::query()->create([
        'ingredient_id' => $ham->id,
        'size_id' => $medium->id,
        'extra_price' => '2.50',
    ]);

    $sencilla = Pizza::query()->create([
        'category_id' => $sencillas->id,
        'pizza_name' => 'Americana',
        'description' => 'Pizza americana.',
        'image_url' => null,
        'is_visible' => true,
    ]);

    $especial = Pizza::query()->create([
        'category_id' => $especiales->id,
        'pizza_name' => 'Suprema Especial',
        'description' => 'Pizza especial.',
        'image_url' => null,
        'is_visible' => true,
    ]);

    $hidden = Pizza::query()->create([
        'category_id' => $sencillas->id,
        'pizza_name' => 'Pizza Oculta',
        'description' => null,
        'image_url' => null,
        'is_visible' => false,
    ]);

    $unavailable = Pizza::query()->create([
        'category_id' => $withoutPrice->id,
        'pizza_name' => 'Pizza Sin Precio',
        'description' => null,
        'image_url' => null,
        'is_visible' => true,
    ]);

    $sencilla->ingredients()->attach([
        $cheese->id,
        $ham->id,
    ]);

    $especial->ingredients()->attach([
        $cheese->id,
    ]);

    return [
        'sencillas' => $sencillas,
        'especiales' => $especiales,
        'without_price' => $withoutPrice,
        'small' => $small,
        'medium' => $medium,
        'unavailable_size' => $unavailableSize,
        'sencilla' => $sencilla,
        'especial' => $especial,
        'hidden' => $hidden,
        'unavailable' => $unavailable,
        'cheese' => $cheese,
        'ham' => $ham,
    ];
}

it(
    'returns categories ordered by name with only positive prices',
    function (): void {
        /** @var TestCase $this */
        [
            'sencillas' => $sencillas,
            'especiales' => $especiales,
            'without_price' => $withoutPrice,
            'small' => $small,
            'medium' => $medium,
        ] = publicCatalogData();

        $response = $this
            ->getJson('/api/v1/public/catalog/categories')
            ->assertOk()
            ->assertJsonCount(
                3,
                'data',
            );

        expect(
            $response->json('data.0.name'),
        )->toBe('Especiales');

        expect(
            $response->json('data.1.name'),
        )->toBe('Sencillas');

        expect(
            $response->json('data.2.name'),
        )->toBe('Sin precios');

        $response
            ->assertJsonPath(
                'data.0.id',
                (int) $especiales->id,
            )
            ->assertJsonCount(
                1,
                'data.0.size_prices',
            )
            ->assertJsonPath(
                'data.0.size_prices.0.size.id',
                (int) $medium->id,
            )
            ->assertJsonPath(
                'data.0.size_prices.0.price',
                15,
            )
            ->assertJsonPath(
                'data.1.id',
                (int) $sencillas->id,
            )
            ->assertJsonCount(
                2,
                'data.1.size_prices',
            )
            ->assertJsonPath(
                'data.1.size_prices.0.size.id',
                (int) $small->id,
            )
            ->assertJsonPath(
                'data.1.size_prices.0.price',
                8.5,
            )
            ->assertJsonPath(
                'data.1.size_prices.1.size.id',
                (int) $medium->id,
            )
            ->assertJsonPath(
                'data.1.size_prices.1.price',
                12,
            )
            ->assertJsonPath(
                'data.2.id',
                (int) $withoutPrice->id,
            )
            ->assertJsonPath(
                'data.2.size_prices',
                [],
            );
    },
);

it(
    'returns ingredients ordered by name with only positive extra prices',
    function (): void {
        /** @var TestCase $this */
        [
            'cheese' => $cheese,
            'ham' => $ham,
            'small' => $small,
            'medium' => $medium,
        ] = publicCatalogData();

        $response = $this
            ->getJson('/api/v1/public/catalog/ingredients')
            ->assertOk()
            ->assertJsonCount(
                2,
                'data',
            );

        expect(
            $response->json('data.0.name'),
        )->toBe('Jamón');

        expect(
            $response->json('data.1.name'),
        )->toBe('Queso');

        $response
            ->assertJsonPath(
                'data.0.id',
                (int) $ham->id,
            )
            ->assertJsonCount(
                1,
                'data.0.extra_prices',
            )
            ->assertJsonPath(
                'data.0.extra_prices.0.size.id',
                (int) $medium->id,
            )
            ->assertJsonPath(
                'data.0.extra_prices.0.extra_price',
                2.5,
            )
            ->assertJsonPath(
                'data.1.id',
                (int) $cheese->id,
            )
            ->assertJsonCount(
                2,
                'data.1.extra_prices',
            )
            ->assertJsonPath(
                'data.1.extra_prices.0.size.id',
                (int) $small->id,
            )
            ->assertJsonPath(
                'data.1.extra_prices.0.extra_price',
                1.25,
            )
            ->assertJsonPath(
                'data.1.extra_prices.1.size.id',
                (int) $medium->id,
            )
            ->assertJsonPath(
                'data.1.extra_prices.1.extra_price',
                2,
            );
    },
);

it(
    'returns only visible pizzas with commercially available prices',
    function (): void {
        /** @var TestCase $this */
        [
            'sencilla' => $sencilla,
            'especial' => $especial,
            'hidden' => $hidden,
            'unavailable' => $unavailable,
            'cheese' => $cheese,
        ] = publicCatalogData();

        $response = $this
            ->getJson('/api/v1/public/catalog/pizzas')
            ->assertOk()
            ->assertJsonCount(
                2,
                'data',
            );

        expect(
            collect($response->json('data'))
                ->pluck('id')
                ->all(),
        )->toBe([
            $sencilla->id,
            $especial->id,
        ]);

        expect(
            collect($response->json('data'))
                ->pluck('id')
                ->all(),
        )
            ->not
            ->toContain($hidden->id)
            ->not
            ->toContain($unavailable->id);

        $response
            ->assertJsonPath(
                'data.0.name',
                'Americana',
            )
            ->assertJsonPath(
                'data.0.category.name',
                'Sencillas',
            )
            ->assertJsonCount(
                2,
                'data.0.category.size_prices',
            )
            ->assertJsonCount(
                2,
                'data.0.ingredients',
            )
            ->assertJsonPath(
                'data.0.ingredients.0.id',
                (int) $cheese->id,
            )
            ->assertJsonPath(
                'data.1.name',
                'Suprema Especial',
            )
            ->assertJsonPath(
                'data.1.category.name',
                'Especiales',
            );
    },
);

it(
    'filters simple and special pizza endpoints by exact category name',
    function (): void {
        /** @var TestCase $this */
        [
            'sencilla' => $sencilla,
            'especial' => $especial,
        ] = publicCatalogData();

        $this
            ->getJson(
                '/api/v1/public/catalog/pizzas/sencillas',
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data',
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $sencilla->id,
            )
            ->assertJsonPath(
                'data.0.category.name',
                'Sencillas',
            );

        $this
            ->getJson(
                '/api/v1/public/catalog/pizzas/especiales',
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data',
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $especial->id,
            )
            ->assertJsonPath(
                'data.0.category.name',
                'Especiales',
            );
    },
);

it(
    'searches visible pizzas by partial name without exposing unavailable pizzas',
    function (): void {
        /** @var TestCase $this */
        [
            'especial' => $especial,
        ] = publicCatalogData();

        $this
            ->getJson(
                '/api/v1/public/catalog/pizzas/Suprema/search',
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data',
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $especial->id,
            )
            ->assertJsonPath(
                'data.0.name',
                'Suprema Especial',
            );

        $this
            ->getJson(
                '/api/v1/public/catalog/pizzas/Oculta/search',
            )
            ->assertOk()
            ->assertJsonPath(
                'data',
                [],
            );

        $this
            ->getJson(
                '/api/v1/public/catalog/pizzas/Sin%20Precio/search',
            )
            ->assertOk()
            ->assertJsonPath(
                'data',
                [],
            );
    },
);

it(
    'returns an empty collection when a pizza search has no matches',
    function (): void {
        /** @var TestCase $this */
        publicCatalogData();

        $this
            ->getJson(
                '/api/v1/public/catalog/pizzas/NoExiste/search',
            )
            ->assertOk()
            ->assertJsonPath(
                'data',
                [],
            );
    },
);
