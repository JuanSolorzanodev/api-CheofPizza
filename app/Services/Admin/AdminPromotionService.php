<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Promotion;
use App\Models\PromotionDetail;
use App\Models\PromotionSizePrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminPromotionService
{
    /**
     * @return Collection<int, Promotion>
     */
    public function promotions(): Collection
    {
        return Promotion::query()
            ->with($this->relations())
            ->withCount([
                'cartItems',
                'orderItems',
            ])
            ->orderByDesc('is_active')
            ->orderBy('promotion_name')
            ->get()
            ->each(
                fn (Promotion $promotion): Promotion => $this->appendDeleteState(
                    $promotion
                )
            );
    }

    public function promotion(
        Promotion $promotion
    ): Promotion {
        $promotion->load(
            $this->relations()
        );

        $promotion->loadCount([
            'cartItems',
            'orderItems',
        ]);

        return $this->appendDeleteState(
            $promotion
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(
        array $data
    ): Promotion {
        return DB::transaction(
            function () use ($data): Promotion {
                $promotion =
                    Promotion::query()->create(
                        $this->promotionValues(
                            $data
                        )
                    );

                $this->syncConfiguration(
                    $promotion,
                    $data
                );

                return $this->promotion(
                    $promotion
                );
            }
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(
        Promotion $promotion,
        array $data
    ): Promotion {
        return DB::transaction(
            function () use (
                $promotion,
                $data
            ): Promotion {
                $promotion->forceFill(
                    $this->promotionValues(
                        $data
                    )
                )->save();

                $this->syncConfiguration(
                    $promotion,
                    $data
                );

                return $this->promotion(
                    $promotion->fresh()
                );
            }
        );
    }

    public function updateVisibility(
        Promotion $promotion,
        bool $isActive
    ): Promotion {
        if ($isActive) {
            $this->assertCanActivate(
                $promotion
            );
        }

        $promotion->forceFill([
            'is_active' => $isActive,
        ])->save();

        return $this->promotion(
            $promotion->fresh()
        );
    }

    /**
     * @throws ValidationException
     */
    public function delete(
        Promotion $promotion
    ): void {
        $promotion->loadCount([
            'cartItems',
            'orderItems',
        ]);

        if (
            (int) $promotion
                ->order_items_count > 0
        ) {
            throw ValidationException::withMessages([
                'promotion' => 'No puedes eliminar esta promoción porque está registrada en pedidos históricos. Desactívala en lugar de eliminarla.',
            ]);
        }

        if (
            (int) $promotion
                ->cart_items_count > 0
        ) {
            throw ValidationException::withMessages([
                'promotion' => 'No puedes eliminar esta promoción porque está siendo utilizada en carritos.',
            ]);
        }

        DB::transaction(
            static function () use (
                $promotion
            ): void {
                $promotion
                    ->promotionDetails()
                    ->delete();

                $promotion
                    ->sizePrices()
                    ->delete();

                $promotion->delete();
            }
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function promotionValues(
        array $data
    ): array {
        $isFixedCombo =
            $data['type'] ===
            Promotion::TYPE_FIXED_COMBO;

        return [
            'promotion_name' => trim(
                (string) $data['name']
            ),

            'slug' => trim(
                (string) $data['slug']
            ),

            'description' => $this->nullableText(
                $data['description']
                ?? null
            ),

            'banner_image_url' => $this->nullableText(
                $data['banner_image_url']
                ?? null
            ),

            'promotion_type' => (string) $data['type'],

            'selection_quantity' => (int) $data[
                    'selection_quantity'
                ],

            'promotion_price' => $isFixedCombo
                    ? round(
                        (float) $data['price'],
                        2
                    )
                    : 0,

            'starts_at' => $data['starts_at'],

            'ends_at' => $data['ends_at'],

            'is_active' => (bool) $data['is_active'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncConfiguration(
        Promotion $promotion,
        array $data
    ): void {
        if (
            $promotion->promotion_type ===
            Promotion::TYPE_FIXED_COMBO
        ) {
            $promotion
                ->sizePrices()
                ->delete();

            $promotion
                ->promotionDetails()
                ->delete();

            foreach (
                $data['details'] ?? [] as $detail
            ) {
                PromotionDetail::query()
                    ->create([
                        'promotion_id' => (int) $promotion->id,

                        'category_id' => (int) $detail[
                                'category_id'
                            ],

                        'size_id' => (int) $detail[
                                'size_id'
                            ],

                        'required_quantity' => (int) $detail[
                                'required_quantity'
                            ],
                    ]);
            }

            return;
        }

        $promotion
            ->promotionDetails()
            ->delete();

        $sizePrices = collect(
            $data['size_prices'] ?? []
        )
            ->map(
                static fn (
                    array $row
                ): array => [
                    'promotion_id' => (int) $promotion->id,

                    'size_id' => (int) $row['size_id'],

                    'fixed_price' => round(
                        (float) $row['price'],
                        2
                    ),

                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            )
            ->values();

        $sizeIds = $sizePrices
            ->pluck('size_id')
            ->all();

        PromotionSizePrice::query()
            ->where(
                'promotion_id',
                $promotion->id
            )
            ->when(
                $sizeIds !== [],
                static fn ($query) => $query->whereNotIn(
                    'size_id',
                    $sizeIds
                )
            )
            ->delete();

        if ($sizePrices->isNotEmpty()) {
            PromotionSizePrice::query()
                ->upsert(
                    $sizePrices->all(),
                    [
                        'promotion_id',
                        'size_id',
                    ],
                    [
                        'fixed_price',
                        'updated_at',
                    ]
                );
        }
    }

    private function assertCanActivate(
        Promotion $promotion
    ): void {
        $promotion->load([
            'promotionDetails',
            'sizePrices',
        ]);

        if (
            $promotion->starts_at === null ||
            $promotion->ends_at === null ||
            ! $promotion->ends_at->greaterThan(
                $promotion->starts_at
            )
        ) {
            throw ValidationException::withMessages([
                'is_active' => 'La promoción necesita un periodo de vigencia válido.',
            ]);
        }

        if (
            $promotion->promotion_type ===
            Promotion::TYPE_FIXED_COMBO
        ) {
            if (
                $promotion
                    ->promotionDetails
                    ->isEmpty() ||
                (float) $promotion
                    ->promotion_price <= 0
            ) {
                throw ValidationException::withMessages([
                    'is_active' => 'El combo necesita reglas y un precio válido antes de activarse.',
                ]);
            }

            return;
        }

        if (
            $promotion
                ->sizePrices
                ->isEmpty()
        ) {
            throw ValidationException::withMessages([
                'is_active' => 'Configura al menos un precio por tamaño antes de activar la promoción.',
            ]);
        }
    }

    private function appendDeleteState(
        Promotion $promotion
    ): Promotion {
        $usage =
            (int) (
                $promotion
                    ->cart_items_count
                ?? 0
            ) +
            (int) (
                $promotion
                    ->order_items_count
                ?? 0
            );

        $promotion->setAttribute(
            'can_delete',
            $usage === 0
        );

        return $promotion;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function relations(): array
    {
        return [
            'promotionDetails' => static fn ($query) => $query
                ->with([
                    'category:id,category_name',
                    'size:id,size_name,portion',
                ])
                ->orderBy('id'),

            'sizePrices' => static fn ($query) => $query
                ->with(
                    'size:id,size_name,portion'
                )
                ->join(
                    'sizes',
                    'sizes.id',
                    '=',
                    'promotion_size_prices.size_id'
                )
                ->select(
                    'promotion_size_prices.*'
                )
                ->orderBy(
                    'sizes.portion'
                ),
        ];
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
