<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paypal_webhook_events', function (Blueprint $table): void {
            $table->id();

            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->string('resource_type')->nullable();

            $table->string('provider_order_id')->nullable()->index();
            $table->string('provider_capture_id')->nullable()->index();

            $table->string('verification_status')->nullable();
            $table->string('processing_status')->default('received');

            $table->json('payload');
            $table->text('failure_message')->nullable();

            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index([
                'event_type',
                'processing_status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paypal_webhook_events');
    }
};
