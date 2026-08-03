<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'cash_movements',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('cash_session_id')
                    ->constrained('cash_sessions')
                    ->cascadeOnDelete();

                $table->foreignId('created_by')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->enum(
                    'type',
                    ['income', 'expense'],
                );

                $table->decimal(
                    'amount',
                    10,
                    2,
                );

                $table->string(
                    'reason',
                    150,
                );

                $table->timestamp(
                    'occurred_at',
                );

                $table->timestamps();

                $table->index([
                    'cash_session_id',
                    'occurred_at',
                ]);

                $table->index([
                    'type',
                    'occurred_at',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'cash_movements'
        );
    }
};
