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
            'cash_sessions',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('opened_by')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId('closed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->enum(
                    'status',
                    ['open', 'closed'],
                )->default('open');

                $table->decimal(
                    'opening_amount',
                    10,
                    2,
                );

                $table->decimal(
                    'expected_cash',
                    10,
                    2,
                )->nullable();

                $table->decimal(
                    'counted_cash',
                    10,
                    2,
                )->nullable();

                $table->decimal(
                    'difference',
                    10,
                    2,
                )->nullable();

                $table->timestamp('opened_at');
                $table->timestamp('closed_at')
                    ->nullable();

                $table->string(
                    'opening_note',
                    255,
                )->nullable();

                $table->string(
                    'closing_note',
                    255,
                )->nullable();

                $table->timestamps();

                $table->index([
                    'status',
                    'opened_at',
                ]);

                $table->index([
                    'opened_by',
                    'opened_at',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'cash_sessions'
        );
    }
};
