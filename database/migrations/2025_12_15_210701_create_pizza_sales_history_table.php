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
        Schema::create('pizza_sales_history', function (Blueprint $table) {
            $table->id();
            $table->date('date');                       // fecha :contentReference[oaicite:32]{index=32}
            $table->foreignId('pizza_id')
            ->constrained('pizzas')
            ->restrictOnDelete();
            $table->foreignId('size_id')
            ->constrained('sizes')
            ->restrictOnDelete();
            $table->integer('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pizza_sales_history');
    }
};
