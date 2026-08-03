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
            'payment_receipts',
            function (Blueprint $table): void {
                $table->id();

                $table->uuid('uuid')
                    ->unique();

                $table->foreignId('order_id')
                    ->constrained('orders')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->string('disk', 50)
                    ->default('payment_receipts');

                $table->string(
                    'file_path',
                    1024,
                )->nullable();

                $table->string(
                    'original_name',
                    255,
                );

                $table->string(
                    'mime_type',
                    100,
                );

                $table->unsignedBigInteger(
                    'file_size',
                );

                $table->string(
                    'status',
                    20,
                )
                    ->default('pending')
                    ->index();

                $table->text(
                    'rejection_reason',
                )->nullable();

                $table->timestamp(
                    'submitted_at',
                )->useCurrent();

                $table->timestamp(
                    'reviewed_at',
                )->nullable();

                $table->foreignId(
                    'reviewed_by',
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp(
                    'expires_at',
                )
                    ->nullable()
                    ->index();

                $table->timestamp(
                    'file_deleted_at',
                )->nullable();

                $table->timestamps();

                $table->index([
                    'order_id',
                    'status',
                ]);

                $table->index([
                    'user_id',
                    'submitted_at',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'payment_receipts',
        );
    }
};
