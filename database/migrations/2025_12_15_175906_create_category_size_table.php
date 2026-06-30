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
        Schema::create('category_size', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete()
                ->cascadeOnDelete();
            $table->foreignId('size_id')
                ->constrained('sizes')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->decimal('price', 8, 2);
            $table->timestamps();
            $table->unique([
                'category_id',
                'size_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_size');
    }
};
