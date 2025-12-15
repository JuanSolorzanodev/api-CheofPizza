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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')               // usuario_id :contentReference[oaicite:23]{index=23}
            ->constrained('users')
            ->cascadeOnDelete();

            $table->foreignId('order_id')              // pedido_id
            ->constrained('orders')
            ->cascadeOnDelete();

            $table->text('message'); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
