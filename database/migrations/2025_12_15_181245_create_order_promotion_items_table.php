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
        Schema::create('order_promotion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')         // detalle_pedido_id
            ->constrained('order_items')
            ->cascadeOnDelete();

            $table->foreignId('pizza_id')
            ->constrained('pizzas')
            ->cascadeOnDelete();

            $table->string('pizza_name', 150)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_promotion_items');
    }
};
