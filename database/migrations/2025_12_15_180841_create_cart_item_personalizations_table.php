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
        Schema::create('cart_item_personalizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_item_id')
                ->constrained('cart_items')
                ->cascadeOnDelete();

            $table->foreignId('ingredient_id')
                ->constrained('ingredients')
                ->restrictOnDelete();
                
            $table->foreignId('personalization_action_id') // accion_id
            ->constrained('personalization_actions')
            ->restrictOnDelete();

            $table->decimal('extra_price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_item_personalizations');
    }
};
