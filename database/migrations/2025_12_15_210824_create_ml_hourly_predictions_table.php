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
        Schema::create('ml_hourly_predictions', function (Blueprint $table) {
            $table->id();
            $table->date('prediction_date');            // fecha_prediccion :contentReference[oaicite:34]{index=34}
            $table->time('hour');
            $table->integer('estimated_quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ml_hourly_predictions');
    }
};
