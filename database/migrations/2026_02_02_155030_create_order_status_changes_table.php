<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_changes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('from_order_status_id')
                ->nullable()
                ->constrained('order_statuses')
                ->nullOnDelete();

            $table->foreignId('to_order_status_id')
                ->constrained('order_statuses')
                ->restrictOnDelete();

            // Si luego agregas operativo/admin, guardas quién lo cambió
            $table->foreignId('changed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('changed_at');
            $table->string('note', 255)->nullable();

            $table->timestamps();

            $table->index(['order_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_changes');
    }
};
