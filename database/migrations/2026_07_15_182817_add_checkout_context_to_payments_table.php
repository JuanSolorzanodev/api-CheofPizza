<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->json('checkout_context')
                ->nullable()
                ->after('provider_metadata');

            $table->char('cart_fingerprint', 64)
                ->nullable()
                ->after('checkout_context');

            $table->index(
                ['cart_id', 'cart_fingerprint'],
                'payments_cart_fingerprint_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(
                'payments_cart_fingerprint_index'
            );

            $table->dropColumn([
                'checkout_context',
                'cart_fingerprint',
            ]);
        });
    }
};
