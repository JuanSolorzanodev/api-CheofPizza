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
        Schema::create('order_item_personalizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')         // detalle_pedido_id
            ->constrained('order_items')
            ->cascadeOnDelete();

            $table->foreignId('ingredient_id')
            ->constrained('ingredients')
            ->restrictOnDelete();

            $table->string('ingredient_name', 150)->nullable(); // ingrediente (snapshot)

            $table->foreignId('personalization_action_id') // accion_id
            ->constrained('personalization_actions')
            ->restrictOnDelete();

            $table->string('modification_type', 80)->nullable(); // tipo_modificacion
            $table->decimal('extra_price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_personalizations');
    }
};
