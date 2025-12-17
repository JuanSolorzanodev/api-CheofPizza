<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Category;
use App\Models\Promotion;
use App\Models\PromotionDetail;
use App\Models\Size;
use Illuminate\Database\Seeder;


class PromotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sencillas  = Category::where('category_name', 'Sencillas')->firstOrFail();
        $especiales = Category::where('category_name', 'Especiales')->firstOrFail();

        $mediana  = Size::where('size_name', 'Mediana')->firstOrFail();
        $familiar = Size::where('size_name', 'Familiar')->firstOrFail();

        // 2x1 MEDIANA (incluye 1 Sencilla + 1 Especial)
        $promoMediana = Promotion::updateOrCreate(
            ['promotion_name' => '2x1 Mediana (1 Sencilla + 1 Especial)'],
            [
                'description' => 'Incluye 1 pizza Sencilla + 1 pizza Especial tamaño Mediana',
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

        // 2x1 FAMILIAR (incluye 1 Sencilla + 1 Especial)
        $promoFamiliar = Promotion::updateOrCreate(
            ['promotion_name' => '2x1 Familiar (1 Sencilla + 1 Especial)'],
            [
                'description' => 'Incluye 1 pizza Sencilla + 1 pizza Especial tamaño Familiar',
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
