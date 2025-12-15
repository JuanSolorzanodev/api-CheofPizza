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
        Schema::create('emission_types', function (Blueprint $table) {
            $table->id();
            $table->string('emission_name', 80);        // nombre_emision :contentReference[oaicite:36]{index=36}
            $table->string('code', 10);  
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emission_types');
    }
};
