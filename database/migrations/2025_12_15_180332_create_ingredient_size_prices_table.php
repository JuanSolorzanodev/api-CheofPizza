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
        Schema::create('ingredient_size_prices', function (Blueprint $table) {
            $table->id();
        // 🔗 FK a ingredients
        $table->foreignId('ingredient_id')
            ->constrained('ingredients')
            ->cascadeOnDelete();

        // 🔗 FK a sizes
        $table->foreignId('size_id')
            ->constrained('sizes')
            ->cascadeOnDelete();

        $table->decimal('extra_price', 10, 2)->default(0);
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_size_prices');
    }
};
