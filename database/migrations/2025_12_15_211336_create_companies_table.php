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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('ruc', 20)->unique();              // numeric string :contentReference[oaicite:38]{index=38}
            $table->string('business_name', 200);             // razon_social
            $table->string('headquarters_address', 255);      // direccion_matriz
            $table->string('establishment_code', 10);         // codigo_establecimiento
            $table->string('emission_point_code', 10);        // codigo_punto_emision
            $table->string('special_taxpayer', 20)->nullable(); // contribuyente_especial
            $table->boolean('accounting_required')->default(false); // obligado_contabilidad
            $table->string('logo_path', 255)->nullable();     // logo_empresa (optional)

            $table->foreignId('environment_type_id')          // ambiente_id
            ->constrained('environment_types')
            ->restrictOnDelete();

            $table->foreignId('emission_type_id')             // emision_id
            ->constrained('emission_types')
            ->restrictOnDelete();

            $table->string('signature_path', 255);            // firma_path
            $table->string('signature_password', 255);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
