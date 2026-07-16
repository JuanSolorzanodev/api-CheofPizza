<?php

declare(strict_types=1);

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Public and idempotency identifiers
            |--------------------------------------------------------------------------
            */

            $table->uuid('uuid')->unique();

            $table->uuid('idempotency_key')->unique();

            /*
            |--------------------------------------------------------------------------
            | Local domain relationships
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('cart_id')
                ->nullable()
                ->constrained('carts')
                ->nullOnDelete();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Provider information
            |--------------------------------------------------------------------------
            */

            $table->string('provider', 30)
                ->default(PaymentProvider::PAYPAL->value);

            $table->string('provider_order_id', 100)
                ->nullable();

            $table->string('provider_capture_id', 100)
                ->nullable();

            $table->string('provider_status', 50)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Financial information
            |--------------------------------------------------------------------------
            */

            $table->decimal('amount', 10, 2);

            $table->char('currency', 3)
                ->default('USD');

            $table->string('status', 40)
                ->default(PaymentStatus::CREATED->value);

            /*
            |--------------------------------------------------------------------------
            | Failure information
            |--------------------------------------------------------------------------
            */

            $table->string('failure_code', 100)
                ->nullable();

            $table->text('failure_message')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Provider metadata
            |--------------------------------------------------------------------------
            |
            | Este campo no almacenará datos completos de tarjeta ni CVV.
            |
            */

            $table->json('provider_metadata')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Lifecycle timestamps
            |--------------------------------------------------------------------------
            */

            $table->timestamp('approved_at')
                ->nullable();

            $table->timestamp('paid_at')
                ->nullable();

            $table->timestamp('failed_at')
                ->nullable();

            $table->timestamp('cancelled_at')
                ->nullable();

            $table->timestamp('refunded_at')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes and uniqueness constraints
            |--------------------------------------------------------------------------
            */

            $table->unique(
                ['provider', 'provider_order_id'],
                'payments_provider_order_unique'
            );

            $table->unique(
                ['provider', 'provider_capture_id'],
                'payments_provider_capture_unique'
            );

            $table->index(
                ['user_id', 'status'],
                'payments_user_status_index'
            );

            $table->index(
                ['cart_id', 'status'],
                'payments_cart_status_index'
            );

            $table->index(
                ['order_id', 'status'],
                'payments_order_status_index'
            );

            $table->index(
                ['provider', 'status'],
                'payments_provider_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
