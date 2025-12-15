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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('promotion_name', 150);     // nombre_promocion :contentReference[oaicite:11]{index=11}
            $table->text('description')->nullable();   // descripcion
            $table->decimal('promotion_price', 10, 2); // precio_promocion
            $table->dateTime('starts_at');             // fecha_inicio
            $table->dateTime('ends_at');               // fecha_fin
            $table->boolean('is_active')->default(true); // activa
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
