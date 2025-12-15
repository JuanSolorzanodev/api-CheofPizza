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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')              // pedido_id
            ->constrained('orders')
            ->cascadeOnDelete();

            $table->foreignId('promotion_id')
            ->nullable()
            ->constrained('promotions')
            ->nullOnDelete();

            $table->string('promotion_name', 150)->nullable(); // nombre_promocion

            $table->foreignId('pizza_id')
            ->nullable()
            ->constrained('pizzas')
            ->nullOnDelete();

            $table->string('pizza_name', 150)->nullable();     // nombre_pizza

            $table->foreignId('size_id')
            ->nullable()
            ->constrained('sizes')
            ->nullOnDelete();

            $table->string('size_name', 60)->nullable();       // tamaño (snapshot)
            $table->string('category_name', 120)->nullable();  // categoria (snapshot)

            $table->integer('quantity');                 // cantidad :contentReference[oaicite:22]{index=22}
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
