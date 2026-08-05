<?php

declare(strict_types=1);

namespace App\Services\Admin\Catalog;

use App\Models\Pizza;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminPizzaService
{
    /**
     * @return Collection<int, Pizza>
     */
    public function pizzas(): Collection
    {
        return Pizza::query()
            ->with([
                'category:id,category_name',

                'ingredients' => static function ($query): void {
                    $query
                        ->select(
                            'ingredients.id',
                            'ingredient_type_id',
                            'ingredient_name'
                        )
                        ->with(
                            'ingredientType:id,type_name'
                        )
                        ->orderBy('ingredient_name');
                },
            ])
            ->withCount('ingredients')
            ->orderBy('pizza_name')
            ->get()
            ->each(
                fn (Pizza $pizza): Pizza => $this->appendUsageState($pizza)
            );
    }

    public function pizza(Pizza $pizza): Pizza
    {
        $pizza->load([
            'category:id,category_name',

            'ingredients' => static function ($query): void {
                $query
                    ->select(
                        'ingredients.id',
                        'ingredient_type_id',
                        'ingredient_name'
                    )
                    ->with(
                        'ingredientType:id,type_name'
                    )
                    ->orderBy('ingredient_name');
            },
        ]);

        $pizza->loadCount('ingredients');

        return $this->appendUsageState($pizza);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Pizza
    {
        return DB::transaction(
            function () use ($data): Pizza {
                $pizza = Pizza::query()->create([
                    'category_id' => (int) $data['category_id'],

                    'pizza_name' => trim(
                        (string) $data['name']
                    ),

                    'description' => $this->nullableText(
                        $data['description'] ?? null
                    ),

                    'image_url' => $this->nullableText(
                        $data['image_url'] ?? null
                    ),

                    'is_visible' => (bool) $data['is_visible'],
                ]);

                $pizza->ingredients()->sync(
                    $this->ingredientIds(
                        $data['ingredient_ids'] ?? []
                    )
                );

                return $this->pizza($pizza);
            }
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(
        Pizza $pizza,
        array $data
    ): Pizza {
        return DB::transaction(
            function () use (
                $pizza,
                $data
            ): Pizza {
                $pizza->forceFill([
                    'category_id' => (int) $data['category_id'],

                    'pizza_name' => trim(
                        (string) $data['name']
                    ),

                    'description' => $this->nullableText(
                        $data['description'] ?? null
                    ),

                    'image_url' => $this->nullableText(
                        $data['image_url'] ?? null
                    ),

                    'is_visible' => (bool) $data['is_visible'],
                ])->save();

                $pizza->ingredients()->sync(
                    $this->ingredientIds(
                        $data['ingredient_ids'] ?? []
                    )
                );

                return $this->pizza(
                    $pizza->fresh()
                );
            }
        );
    }

    public function updateVisibility(
        Pizza $pizza,
        bool $isVisible
    ): Pizza {
        $pizza->forceFill([
            'is_visible' => $isVisible,
        ])->save();

        return $this->pizza(
            $pizza->fresh()
        );
    }

    /**
     * @throws ValidationException
     */
    public function delete(Pizza $pizza): void
    {
        $usage = $this->usageCounts(
            (int) $pizza->id
        );

        if (
            $usage['order_items'] > 0 ||
            $usage['order_items_second'] > 0 ||
            $usage['order_promotions'] > 0 ||
            $usage['sales_history'] > 0
        ) {
            throw ValidationException::withMessages([
                'pizza' => 'No puedes eliminar esta pizza porque tiene pedidos o información histórica. Ocúltala del catálogo en lugar de eliminarla.',
            ]);
        }

        if (
            $usage['cart_items'] > 0 ||
            $usage['cart_items_second'] > 0 ||
            $usage['cart_promotions'] > 0
        ) {
            throw ValidationException::withMessages([
                'pizza' => 'No puedes eliminar esta pizza porque está siendo utilizada en carritos activos. Ocúltala temporalmente.',
            ]);
        }

        DB::transaction(
            static function () use ($pizza): void {
                $pizza
                    ->ingredients()
                    ->detach();

                $pizza->delete();
            }
        );
    }

    /**
     * @return array{
     *     cart_items: int,
     *     cart_items_second: int,
     *     cart_promotions: int,
     *     order_items: int,
     *     order_items_second: int,
     *     order_promotions: int,
     *     sales_history: int
     * }
     */
    private function usageCounts(
        int $pizzaId
    ): array {
        return [
            'cart_items' => DB::table('cart_items')
                ->where(
                    'pizza_id',
                    $pizzaId
                )
                ->count(),

            'cart_items_second' => DB::table('cart_items')
                ->where(
                    'pizza_id_second',
                    $pizzaId
                )
                ->count(),

            'cart_promotions' => DB::table('cart_promotion_items')
                ->where(
                    'pizza_id',
                    $pizzaId
                )
                ->count(),

            'order_items' => DB::table('order_items')
                ->where(
                    'pizza_id',
                    $pizzaId
                )
                ->count(),

            'order_items_second' => DB::table('order_items')
                ->where(
                    'pizza_id_second',
                    $pizzaId
                )
                ->count(),

            'order_promotions' => DB::table('order_promotion_items')
                ->where(
                    'pizza_id',
                    $pizzaId
                )
                ->count(),

            'sales_history' => DB::table('pizza_sales_history')
                ->where(
                    'pizza_id',
                    $pizzaId
                )
                ->count(),
        ];
    }

    private function appendUsageState(
        Pizza $pizza
    ): Pizza {
        $usage = $this->usageCounts(
            (int) $pizza->id
        );

        $pizza->setAttribute(
            'cart_items_count',
            $usage['cart_items']
        );

        $pizza->setAttribute(
            'cart_items_second_count',
            $usage['cart_items_second']
        );

        $pizza->setAttribute(
            'cart_promotion_items_count',
            $usage['cart_promotions']
        );

        $pizza->setAttribute(
            'order_items_count',
            $usage['order_items']
        );

        $pizza->setAttribute(
            'order_items_second_count',
            $usage['order_items_second']
        );

        $pizza->setAttribute(
            'order_promotion_items_count',
            $usage['order_promotions']
        );

        $pizza->setAttribute(
            'pizza_sales_histories_count',
            $usage['sales_history']
        );

        $pizza->setAttribute(
            'usage_total',
            array_sum($usage)
        );

        $pizza->setAttribute(
            'can_delete',
            array_sum($usage) === 0
        );

        return $pizza;
    }

    /**
     * @return array<int, int>
     */
    private function ingredientIds(
        mixed $value
    ): array {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(
                static fn ($id): int => (int) $id
            )
            ->filter(
                static fn (int $id): bool => $id > 0
            )
            ->unique()
            ->values()
            ->all();
    }

    private function nullableText(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }
}
