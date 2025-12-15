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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('identification_type_id') // identificacion_id :contentReference[oaicite:39]{index=39}
            ->constrained('identification_types')
            ->restrictOnDelete();

            $table->string('identification', 30);       // identificacion
            $table->string('business_name', 200);       // razon_social
            $table->string('email')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
