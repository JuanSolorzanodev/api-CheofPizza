<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Promotion;
use App\Models\PromotionDetail;
use App\Models\Size;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $sencillas = Category::where('category_name', 'Sencillas')->firstOrFail();
        $especiales = Category::where('category_name', 'Especiales')->firstOrFail();

        $mediana = Size::where('size_name', 'Mediana')->firstOrFail();
        $familiar = Size::where('size_name', 'Familiar')->firstOrFail();

        $promoMediana = Promotion::updateOrCreate(
            ['slug' => '2x1-mediana'],
            [
                'promotion_name' => '2x1 Mediana (1 Sencilla + 1 Especial)',
                'description' => 'Incluye 1 pizza Sencilla + 1 pizza Especial tamaño Mediana',
                'banner_image_url' => 'https://res.cloudinary.com/dertc9kiq/image/upload/v1766279154/cheofbanner2_acgkhf.png',
                'promotion_price' => 15.00,
                'starts_at' => now()->subDays(7),
                'ends_at' => now()->addDays(60),
                'is_active' => true,
            ]
        );

        PromotionDetail::updateOrCreate(
            ['promotion_id' => $promoMediana->id, 'category_id' => $sencillas->id, 'size_id' => $mediana->id],
            ['required_quantity' => 1]
        );

        PromotionDetail::updateOrCreate(
            ['promotion_id' => $promoMediana->id, 'category_id' => $especiales->id, 'size_id' => $mediana->id],
            ['required_quantity' => 1]
        );

        $promoFamiliar = Promotion::updateOrCreate(
            ['slug' => '2x1-familiar'],
            [
                'promotion_name' => '2x1 Familiar (1 Sencilla + 1 Especial)',
                'description' => 'Incluye 1 pizza Sencilla + 1 pizza Especial tamaño Familiar',
                'banner_image_url' => 'https://res.cloudinary.com/dertc9kiq/image/upload/v1766279154/cheofbanner_jn6lak.png',
                'promotion_price' => 20.00,
                'starts_at' => now()->subDays(7),
                'ends_at' => now()->addDays(60),
                'is_active' => true,
            ]
        );

        PromotionDetail::updateOrCreate(
            ['promotion_id' => $promoFamiliar->id, 'category_id' => $sencillas->id, 'size_id' => $familiar->id],
            ['required_quantity' => 1]
        );

        PromotionDetail::updateOrCreate(
            ['promotion_id' => $promoFamiliar->id, 'category_id' => $especiales->id, 'size_id' => $familiar->id],
            ['required_quantity' => 1]
        );
    }
}
