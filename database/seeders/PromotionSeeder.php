<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Promotion;
use App\Models\PromotionDetail;
use App\Models\PromotionSizePrice;
use App\Models\Size;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(
            function (): void {
                $sencillas = Category::query()
                    ->where(
                        'category_name',
                        'Sencillas'
                    )
                    ->firstOrFail();

                $especiales = Category::query()
                    ->where(
                        'category_name',
                        'Especiales'
                    )
                    ->firstOrFail();

                $mediana = Size::query()
                    ->where(
                        'size_name',
                        'Mediana'
                    )
                    ->firstOrFail();

                $familiar = Size::query()
                    ->where(
                        'size_name',
                        'Familiar'
                    )
                    ->firstOrFail();

                $this->createCombo(
                    slug: '2-medianas-por-15',
                    name: '2 medianas por $15',
                    description:
                        'Incluye una pizza Sencilla y una pizza Especial tamaño Mediana.',
                    banner:
                        'https://res.cloudinary.com/dertc9kiq/image/upload/v1766279154/cheofbanner2_acgkhf.png',
                    price: 15.00,
                    sizeId: (int) $mediana->id,
                    sencillaId: (int) $sencillas->id,
                    especialId: (int) $especiales->id,
                );

                $this->createCombo(
                    slug: '2-familiares-por-20',
                    name: '2 familiares por $20',
                    description:
                        'Incluye una pizza Sencilla y una pizza Especial tamaño Familiar.',
                    banner:
                        'https://res.cloudinary.com/dertc9kiq/image/upload/v1766279154/cheofbanner_jn6lak.png',
                    price: 20.00,
                    sizeId: (int) $familiar->id,
                    sencillaId: (int) $sencillas->id,
                    especialId: (int) $especiales->id,
                );

                $horaLoca = Promotion::query()
                    ->updateOrCreate(
                        [
                            'slug' =>
                                'hora-loca',
                        ],
                        [
                            'promotion_name' =>
                                'Hora Loca',

                            'description' =>
                                'Durante la Hora Loca elige cualquier pizza: Mediana por $5 o Familiar por $10.',

                            'banner_image_url' =>
                                null,

                            'promotion_type' =>
                                Promotion::TYPE_SIZE_FIXED_PRICE,

                            'selection_quantity' =>
                                1,

                            /*
                             * Para esta modalidad el precio
                             * real se obtiene de
                             * promotion_size_prices.
                             */
                            'promotion_price' =>
                                0,

                            /*
                             * Se deja inicialmente fuera de
                             * vigencia. Desde el panel se
                             * programará el día y horario.
                             */
                            'starts_at' =>
                                now(),

                            'ends_at' =>
                                now(),

                            'is_active' =>
                                false,
                        ],
                    );

                $horaLoca
                    ->promotionDetails()
                    ->delete();

                PromotionSizePrice::query()
                    ->updateOrCreate(
                        [
                            'promotion_id' =>
                                $horaLoca->id,

                            'size_id' =>
                                $mediana->id,
                        ],
                        [
                            'fixed_price' =>
                                5.00,
                        ],
                    );

                PromotionSizePrice::query()
                    ->updateOrCreate(
                        [
                            'promotion_id' =>
                                $horaLoca->id,

                            'size_id' =>
                                $familiar->id,
                        ],
                        [
                            'fixed_price' =>
                                10.00,
                        ],
                    );

                PromotionSizePrice::query()
                    ->where(
                        'promotion_id',
                        $horaLoca->id
                    )
                    ->whereNotIn(
                        'size_id',
                        [
                            $mediana->id,
                            $familiar->id,
                        ],
                    )
                    ->delete();
            }
        );
    }

    private function createCombo(
        string $slug,
        string $name,
        string $description,
        ?string $banner,
        float $price,
        int $sizeId,
        int $sencillaId,
        int $especialId,
    ): void {
        $promotion = Promotion::query()
            ->updateOrCreate(
                [
                    'slug' => $slug,
                ],
                [
                    'promotion_name' =>
                        $name,

                    'description' =>
                        $description,

                    'banner_image_url' =>
                        $banner,

                    'promotion_type' =>
                        Promotion::TYPE_FIXED_COMBO,

                    'selection_quantity' =>
                        2,

                    'promotion_price' =>
                        $price,

                    /*
                     * Estas promociones se mantienen
                     * disponibles durante un periodo
                     * amplio. Después se administrarán
                     * desde el panel.
                     */
                    'starts_at' =>
                        now()->startOfDay(),

                    'ends_at' =>
                        now()
                            ->addYears(2)
                            ->endOfDay(),

                    'is_active' =>
                        true,
                ],
            );

        $promotion
            ->sizePrices()
            ->delete();

        PromotionDetail::query()
            ->updateOrCreate(
                [
                    'promotion_id' =>
                        $promotion->id,

                    'category_id' =>
                        $sencillaId,

                    'size_id' =>
                        $sizeId,
                ],
                [
                    'required_quantity' =>
                        1,
                ],
            );

        PromotionDetail::query()
            ->updateOrCreate(
                [
                    'promotion_id' =>
                        $promotion->id,

                    'category_id' =>
                        $especialId,

                    'size_id' =>
                        $sizeId,
                ],
                [
                    'required_quantity' =>
                        1,
                ],
            );

        PromotionDetail::query()
            ->where(
                'promotion_id',
                $promotion->id
            )
            ->where(
                static function ($query) use (
                    $sizeId,
                    $sencillaId,
                    $especialId,
                ): void {
                    $query
                        ->where(
                            'size_id',
                            '!=',
                            $sizeId
                        )
                        ->orWhereNotIn(
                            'category_id',
                            [
                                $sencillaId,
                                $especialId,
                            ]
                        );
                }
            )
            ->delete();
    }
}
