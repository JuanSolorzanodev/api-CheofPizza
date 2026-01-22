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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();

            // Estado / orden (por si luego tienes más de una cuenta)
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('priority')->default(1);

            // Datos bancarios (lo mínimo profesional)
            $table->string('bank_name', 120);
            $table->string('account_type', 30);      // Ahorros | Corriente
            $table->string('account_number', 60);
            $table->string('holder_name', 160);
            $table->string('holder_id', 30)->nullable(); // cédula/RUC opcional

            // QR (imagen) + texto útil para el cliente
            $table->string('qr_image_url', 500)->nullable(); // URL (Cloudinary/S3/tu servidor)
            $table->text('instructions')->nullable();        // Ej: "Enviar comprobante con #orden..."

            // Evitar duplicados
            $table->unique(['bank_name', 'account_number']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
