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
            'pizza_ingredients',
            function (Blueprint $table): void {
                $table->unique(
                    [
                        'pizza_id',
                        'ingredient_id',
                    ],
                    'pizza_ingredients_pizza_ingredient_unique',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::table(
            'pizza_ingredients',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'pizza_ingredients_pizza_ingredient_unique',
                );
            },
        );
    }
};
