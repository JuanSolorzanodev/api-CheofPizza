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
        Schema::create('sales_by_hour', function (Blueprint $table) {
            $table->id();
            $table->date('date');                       // fecha :contentReference[oaicite:33]{index=33}
            $table->time('hour');                       // hora
            $table->integer('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_by_hour');
    }
};
