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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')             // empresa_id :contentReference[oaicite:40]{index=40}
            ->constrained('companies')
            ->restrictOnDelete();

            $table->foreignId('customer_id')            // cliente_id
            ->constrained('customers')
            ->restrictOnDelete();

            $table->string('voucher_type', 2)->default('01'); // tipo_comprobante
            $table->integer('establishment_code');            // codigo_establecimiento
            $table->integer('emission_point_code');           // codigo_punto_emision
            $table->string('sequential', 20);                 // secuencial
            $table->date('issued_on');  
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
