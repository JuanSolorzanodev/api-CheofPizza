<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'ingredients',
            static function (Blueprint $table): void {
                $table->unique(
                    [
                        'ingredient_type_id',
                        'ingredient_name',
                    ],
                    'ingredients_type_name_unique'
                );
            }
        );

        Schema::table(
            'ingredient_size_prices',
            static function (Blueprint $table): void {
                $table->unique(
                    [
                        'ingredient_id',
                        'size_id',
                    ],
                    'ingredient_size_prices_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'ingredient_size_prices',
            static function (Blueprint $table): void {
                $table->dropUnique(
                    'ingredient_size_prices_unique'
                );
            }
        );

        Schema::table(
            'ingredients',
            static function (Blueprint $table): void {
                $table->dropUnique(
                    'ingredients_type_name_unique'
                );
            }
        );
    }
};
