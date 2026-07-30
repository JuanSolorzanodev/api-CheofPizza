<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'promotions',
            static function (Blueprint $table): void {
                $table
                    ->string(
                        'promotion_type',
                        30
                    )
                    ->default('fixed_combo')
                    ->after('banner_image_url');

                $table
                    ->unsignedInteger(
                        'selection_quantity'
                    )
                    ->default(1)
                    ->after('promotion_type');
            }
        );

        Schema::create(
            'promotion_size_prices',
            static function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('promotion_id')
                    ->constrained('promotions')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('size_id')
                    ->constrained('sizes')
                    ->restrictOnDelete();

                $table->decimal(
                    'fixed_price',
                    10,
                    2
                );

                $table->timestamps();

                $table->unique(
                    [
                        'promotion_id',
                        'size_id',
                    ],
                    'promotion_size_prices_unique'
                );
            }
        );

        DB::table('promotions')->update([
            'promotion_type' =>
                'fixed_combo',

            'selection_quantity' => 1,
        ]);

        DB::table('promotions')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(
                static function (
                    object $promotion
                ): void {
                    $quantity = (int) DB::table(
                        'promotion_details'
                    )
                        ->where(
                            'promotion_id',
                            $promotion->id
                        )
                        ->sum(
                            'required_quantity'
                        );

                    DB::table('promotions')
                        ->where(
                            'id',
                            $promotion->id
                        )
                        ->update([
                            'selection_quantity' =>
                                max(1, $quantity),
                        ]);
                }
            );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'promotion_size_prices'
        );

        Schema::table(
            'promotions',
            static function (Blueprint $table): void {
                $table->dropColumn([
                    'promotion_type',
                    'selection_quantity',
                ]);
            }
        );
    }
};
