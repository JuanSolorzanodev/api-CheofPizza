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
        Schema::create('sales_by_size', function (Blueprint $table) {
            $table->id();
            $table->date('date');                       // fecha :contentReference[oaicite:30]{index=30}
            $table->foreignId('size_id')
            ->constrained('sizes')
            ->restrictOnDelete();
            $table->integer('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_by_size');
    }
};
