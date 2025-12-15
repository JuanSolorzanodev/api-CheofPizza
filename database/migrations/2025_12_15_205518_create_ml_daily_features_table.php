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
        Schema::create('ml_daily_features', function (Blueprint $table) {
            $table->id();
            $table->date('date');                       // 
            $table->integer('total_pizzas_sold');
            $table->integer('small_sales');
            $table->integer('medium_sales');
            $table->integer('family_sales');
            $table->integer('giant_sales');
            $table->integer('basic_sales');             // sencillas
            $table->integer('special_sales');           // especiales
            $table->integer('promotion_sales');
            $table->integer('regular_sales');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ml_daily_features');
    }
};
