<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CART ITEMS
        Schema::table('cart_items', function (Blueprint $table) {
            // bandera para saber si el item es mitad y mitad
            $table->boolean('is_half_and_half')
                ->default(false)
                ->after('item_type');

            // segundo sabor (nullable)
            $table->foreignId('pizza_id_second')
                ->nullable()
                ->after('pizza_id')
                ->constrained('pizzas')
                ->cascadeOnDelete(); // coherente con tu cart_items.pizza_id (cascadeOnDelete)
        });

        // ORDER ITEMS
        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('is_half_and_half')
                ->default(false)
                ->after('category_name'); // o después de pizza_id, si prefieres

            $table->foreignId('pizza_id_second')
                ->nullable()
                ->after('pizza_id')
                ->constrained('pizzas')
                ->nullOnDelete(); // coherente con tu order_items.pizza_id (nullOnDelete)

            // snapshot del segundo sabor (para que el pedido quede guardado aunque cambie la pizza)
            $table->string('pizza_name_second', 150)
                ->nullable()
                ->after('pizza_name');

            $table->string('category_name_second', 120)
                ->nullable()
                ->after('category_name');
        });
    }

    public function down(): void
    {
        // ORDER ITEMS
        Schema::table('order_items', function (Blueprint $table) {
            // primero eliminar FK y columnas
            $table->dropForeign(['pizza_id_second']);

            $table->dropColumn([
                'is_half_and_half',
                'pizza_id_second',
                'pizza_name_second',
                'category_name_second',
            ]);
        });

        // CART ITEMS
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['pizza_id_second']);

            $table->dropColumn([
                'is_half_and_half',
                'pizza_id_second',
            ]);
        });
    }
};
