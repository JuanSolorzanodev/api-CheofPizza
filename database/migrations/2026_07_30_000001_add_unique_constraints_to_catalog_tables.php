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
            'categories',
            function (Blueprint $table): void {
                $table->unique(
                    'category_name',
                    'categories_category_name_unique'
                );
            }
        );

        Schema::table(
            'sizes',
            function (Blueprint $table): void {
                $table->unique(
                    'size_name',
                    'sizes_size_name_unique'
                );
            }
        );

        Schema::table(
            'category_size_prices',
            function (Blueprint $table): void {
                $table->unique(
                    [
                        'category_id',
                        'size_id',
                    ],
                    'category_size_prices_category_size_unique'
                );
            }
        );

        Schema::table(
            'ingredient_size_prices',
            function (Blueprint $table): void {
                $table->unique(
                    [
                        'ingredient_id',
                        'size_id',
                    ],
                    'ingredient_size_prices_ingredient_size_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'ingredient_size_prices',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'ingredient_size_prices_ingredient_size_unique'
                );
            }
        );

        Schema::table(
            'category_size_prices',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'category_size_prices_category_size_unique'
                );
            }
        );

        Schema::table(
            'sizes',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'sizes_size_name_unique'
                );
            }
        );

        Schema::table(
            'categories',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'categories_category_name_unique'
                );
            }
        );
    }
};
