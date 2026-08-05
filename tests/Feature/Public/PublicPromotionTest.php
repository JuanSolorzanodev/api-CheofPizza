<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Promotion;
use App\Models\PromotionDetail;
use App\Models\PromotionSizePrice;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     traditional: Category,
 *     premium: Category,
 *     small: Size,
 *     medium: Size,
 *     active_combo: Promotion,
 *     active_size_promotion: Promotion,
 *     inactive: Promotion,
 *     future: Promotion,
 *     expired: Promotion
 * }
 */
function publicPromotionCatalog(): array
{
    $traditional = Category::query()->create([
        'category_name' => 'Tradicionales públicas '.fake()->uuid(),
        'description' => null,
    ]);

    $premium = Category::query()->create([
        'category_name' => 'Premium públicas '.fake()->uuid(),
        'description' => null,
    ]);

    $small = Size::query()->create([
        'size_name' => 'Pequeña pública '.fake()->uuid(),
        'portion' => 4,
    ]);

    $medium = Size::query()->create([
        'size_name' => 'Mediana pública '.fake()->uuid(),
        'portion' => 8,
    ]);

    $activeCombo = Promotion::query()->create([
        'promotion_name' => 'Combo familiar activo',
        'slug' => 'combo-familiar-activo-'.fake()->uuid(),
        'description' => 'Dos pizzas tradicionales.',
        'banner_image_url' => 'https://example.test/combo.jpg',
        'promotion_type' => Promotion::TYPE_FIXED_COMBO,
        'selection_quantity' => 2,
        'promotion_price' => '18.50',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(2),
        'is_active' => true,
    ]);

    PromotionDetail::query()->create([
        'promotion_id' => $activeCombo->id,
        'category_id' => $traditional->id,
        'size_id' => $medium->id,
        'required_quantity' => 2,
    ]);

    $activeSizePromotion = Promotion::query()->create([
        'promotion_name' => 'Precio especial por tamaño',
        'slug' => 'precio-especial-tamano-'.fake()->uuid(),
        'description' => 'Selecciona una pizza.',
        'banner_image_url' => null,
        'promotion_type' => Promotion::TYPE_SIZE_FIXED_PRICE,
        'selection_quantity' => 1,
        'promotion_price' => '0.00',
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->addDays(3),
        'is_active' => true,
    ]);

    PromotionSizePrice::query()->create([
        'promotion_id' => $activeSizePromotion->id,
        'size_id' => $small->id,
        'fixed_price' => '7.50',
    ]);

    PromotionSizePrice::query()->create([
        'promotion_id' => $activeSizePromotion->id,
        'size_id' => $medium->id,
        'fixed_price' => '10.25',
    ]);

    $inactive = Promotion::query()->create([
        'promotion_name' => 'Promoción inactiva',
        'slug' => 'promocion-inactiva-'.fake()->uuid(),
        'description' => null,
        'banner_image_url' => null,
        'promotion_type' => Promotion::TYPE_FIXED_COMBO,
        'selection_quantity' => 1,
        'promotion_price' => '5.00',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'is_active' => false,
    ]);

    $future = Promotion::query()->create([
        'promotion_name' => 'Promoción futura',
        'slug' => 'promocion-futura-'.fake()->uuid(),
        'description' => null,
        'banner_image_url' => null,
        'promotion_type' => Promotion::TYPE_FIXED_COMBO,
        'selection_quantity' => 1,
        'promotion_price' => '6.00',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDays(2),
        'is_active' => true,
    ]);

    $expired = Promotion::query()->create([
        'promotion_name' => 'Promoción vencida',
        'slug' => 'promocion-vencida-'.fake()->uuid(),
        'description' => null,
        'banner_image_url' => null,
        'promotion_type' => Promotion::TYPE_FIXED_COMBO,
        'selection_quantity' => 1,
        'promotion_price' => '7.00',
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->subDay(),
        'is_active' => true,
    ]);

    return [
        'traditional' => $traditional,
        'premium' => $premium,
        'small' => $small,
        'medium' => $medium,
        'active_combo' => $activeCombo,
        'active_size_promotion' => $activeSizePromotion,
        'inactive' => $inactive,
        'future' => $future,
        'expired' => $expired,
    ];
}

it(
    'returns only active promotions inside their validity period',
    function (): void {
        /** @var TestCase $this */
        [
            'active_combo' => $combo,
            'active_size_promotion' => $sizePromotion,
            'inactive' => $inactive,
            'future' => $future,
            'expired' => $expired,
        ] = publicPromotionCatalog();

        $response = $this
            ->getJson('/api/v1/public/promotions')
            ->assertOk()
            ->assertJsonCount(
                2,
                'data',
            );

        $ids = collect(
            $response->json('data'),
        )
            ->pluck('id')
            ->all();

        expect($ids)
            ->toContain(
                $combo->id,
                $sizePromotion->id,
            )
            ->not
            ->toContain(
                $inactive->id,
                $future->id,
                $expired->id,
            );
    },
);

it(
    'orders active promotions alphabetically by name',
    function (): void {
        /** @var TestCase $this */
        publicPromotionCatalog();

        $response = $this
            ->getJson('/api/v1/public/promotions')
            ->assertOk();

        expect(
            collect($response->json('data'))
                ->pluck('name')
                ->all(),
        )->toBe([
            'Combo familiar activo',
            'Precio especial por tamaño',
        ]);
    },
);

it(
    'returns the complete fixed combo structure',
    function (): void {
        /** @var TestCase $this */
        [
            'active_combo' => $promotion,
            'traditional' => $traditional,
            'medium' => $medium,
        ] = publicPromotionCatalog();

        $this
            ->getJson(
                "/api/v1/public/promotions/{$promotion->slug}",
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                (int) $promotion->id,
            )
            ->assertJsonPath(
                'data.slug',
                $promotion->slug,
            )
            ->assertJsonPath(
                'data.name',
                'Combo familiar activo',
            )
            ->assertJsonPath(
                'data.description',
                'Dos pizzas tradicionales.',
            )
            ->assertJsonPath(
                'data.banner_image_url',
                'https://example.test/combo.jpg',
            )
            ->assertJsonPath(
                'data.type',
                Promotion::TYPE_FIXED_COMBO,
            )
            ->assertJsonPath(
                'data.price',
                18.5,
            )
            ->assertJsonCount(
                1,
                'data.details',
            )
            ->assertJsonPath(
                'data.details.0.required_quantity',
                2,
            )
            ->assertJsonPath(
                'data.details.0.category.id',
                (int) $traditional->id,
            )
            ->assertJsonPath(
                'data.details.0.category.name',
                $traditional->category_name,
            )
            ->assertJsonPath(
                'data.details.0.size.id',
                (int) $medium->id,
            )
            ->assertJsonPath(
                'data.details.0.size.name',
                $medium->size_name,
            )
            ->assertJsonPath(
                'data.details.0.size.portion',
                8,
            )
            ->assertJsonPath(
                'data.selection_rules.type',
                Promotion::TYPE_FIXED_COMBO,
            )
            ->assertJsonPath(
                'data.selection_rules.allows_extras',
                true,
            )
            ->assertJsonPath(
                'data.selection_rules.allows_remove_ingredients',
                true,
            )
            ->assertJsonPath(
                'data.selection_rules.allows_half_and_half',
                false,
            )
            ->assertJsonPath(
                'data.selection_rules.allows_any_category',
                false,
            )
            ->assertJsonPath(
                'data.selection_rules.requires_size_selection',
                false,
            )
            ->assertJsonPath(
                'data.selection_rules.selection_count',
                2,
            )
            ->assertJsonPath(
                'data.selection_rules.max_extras_per_pizza',
                8,
            )
            ->assertJsonPath(
                'data.selection_rules.allow_duplicate_ingredients_as_extra',
                false,
            );
    },
);

it(
    'returns size prices and rules for a size fixed price promotion',
    function (): void {
        /** @var TestCase $this */
        [
            'active_size_promotion' => $promotion,
            'small' => $small,
            'medium' => $medium,
        ] = publicPromotionCatalog();

        $this
            ->getJson(
                "/api/v1/public/promotions/{$promotion->slug}",
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                (int) $promotion->id,
            )
            ->assertJsonPath(
                'data.type',
                Promotion::TYPE_SIZE_FIXED_PRICE,
            )
            ->assertJsonPath(
                'data.price',
                0,
            )
            ->assertJsonCount(
                0,
                'data.details',
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
                7.5,
            )
            ->assertJsonPath(
                'data.size_prices.0.size.id',
                (int) $small->id,
            )
            ->assertJsonPath(
                'data.size_prices.0.size.portion',
                4,
            )
            ->assertJsonPath(
                'data.size_prices.1.size_id',
                (int) $medium->id,
            )
            ->assertJsonPath(
                'data.size_prices.1.price',
                10.25,
            )
            ->assertJsonPath(
                'data.selection_rules.allows_any_category',
                true,
            )
            ->assertJsonPath(
                'data.selection_rules.requires_size_selection',
                true,
            )
            ->assertJsonPath(
                'data.selection_rules.selection_count',
                1,
            );
    },
);

it(
    'does not expose inactive future or expired promotions by slug',
    function (): void {
        /** @var TestCase $this */
        [
            'inactive' => $inactive,
            'future' => $future,
            'expired' => $expired,
        ] = publicPromotionCatalog();

        foreach (
            [
                $inactive,
                $future,
                $expired,
            ] as $promotion
        ) {
            $this
                ->getJson(
                    "/api/v1/public/promotions/{$promotion->slug}",
                )
                ->assertNotFound();
        }
    },
);

it(
    'returns not found for an unknown promotion slug',
    function (): void {
        /** @var TestCase $this */
        publicPromotionCatalog();

        $this
            ->getJson(
                '/api/v1/public/promotions/promocion-que-no-existe',
            )
            ->assertNotFound();
    },
);
