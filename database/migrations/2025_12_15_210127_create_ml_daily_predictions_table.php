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
        Schema::create('ml_daily_predictions', function (Blueprint $table) {
            $table->id();
            $table->date('prediction_date');            // fecha_prediccion :contentReference[oaicite:28]{index=28}
            $table->integer('total_pizzas');
            $table->integer('small_pizzas');
            $table->integer('medium_pizzas');
            $table->integer('family_pizzas');
            $table->integer('giant_pizzas');
            $table->integer('basic');
            $table->integer('special');
            $table->integer('estimated_promotions');
            $table->integer('estimated_regular');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ml_daily_predictions');
    }
};
