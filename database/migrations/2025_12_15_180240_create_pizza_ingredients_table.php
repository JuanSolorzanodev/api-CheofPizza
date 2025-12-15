<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pizza_ingredients', function (Blueprint $table) {
            $table->id();
        // 🔗 FK a pizzas
        $table->foreignId('pizza_id')
            ->constrained('pizzas')
            ->cascadeOnDelete();

        // 🔗 FK a ingredients
        $table->foreignId('ingredient_id')
            ->constrained('ingredients')
            ->cascadeOnDelete();

        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pizza_ingredients');
    }
};
