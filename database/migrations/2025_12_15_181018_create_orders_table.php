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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50);        // numero_pedido :contentReference[oaicite:21]{index=21}

            $table->foreignId('user_id')               // usuario_id
            ->constrained('users')
            ->restrictOnDelete();

            $table->dateTime('ordered_at');            // fecha_pedido
            $table->decimal('total', 10, 2);

            $table->foreignId('delivery_type_id')      // entrega_id
            ->constrained('delivery_types')
            ->restrictOnDelete();

            $table->text('address')->nullable();       // direccion

            $table->foreignId('payment_method_id')     // metodo_id
            ->constrained('payment_methods')
            ->restrictOnDelete();

            $table->foreignId('order_status_id')       // estado_id
            ->constrained('order_statuses')
            ->restrictOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
