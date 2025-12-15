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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
        $table->foreignId('cart_id')
            ->constrained('carts')
            ->cascadeOnDelete();

        $table->enum('item_type', ['pizza', 'promotion']);

        $table->foreignId('pizza_id')
            ->nullable()
            ->constrained('pizzas')
            ->cascadeOnDelete();

        $table->foreignId('promotion_id')
            ->nullable()
            ->constrained('promotions')
            ->cascadeOnDelete();

        $table->foreignId('size_id')
            ->nullable()
            ->constrained('sizes')
            ->cascadeOnDelete();

        $table->unsignedInteger('quantity')->default(1);
        $table->decimal('unit_price', 10, 2)->default(0);
        $table->decimal('subtotal', 10, 2)->default(0);
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
