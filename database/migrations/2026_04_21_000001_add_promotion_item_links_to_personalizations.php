<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_item_personalizations', function (Blueprint $table) {
            if (!Schema::hasColumn('cart_item_personalizations', 'cart_promotion_item_id')) {
                $table->foreignId('cart_promotion_item_id')
                    ->nullable()
                    ->after('cart_item_id')
                    ->constrained('cart_promotion_items')
                    ->nullOnDelete();

                $table->index(['cart_item_id', 'cart_promotion_item_id'], 'cip_cart_item_promo_item_idx');
            }
        });

        Schema::table('order_item_personalizations', function (Blueprint $table) {
            if (!Schema::hasColumn('order_item_personalizations', 'order_promotion_item_id')) {
                $table->foreignId('order_promotion_item_id')
                    ->nullable()
                    ->after('order_item_id')
                    ->constrained('order_promotion_items')
                    ->nullOnDelete();

                $table->index(['order_item_id', 'order_promotion_item_id'], 'oip_order_item_promo_item_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_item_personalizations', function (Blueprint $table) {
            if (Schema::hasColumn('order_item_personalizations', 'order_promotion_item_id')) {
                $table->dropIndex('oip_order_item_promo_item_idx');
                $table->dropConstrainedForeignId('order_promotion_item_id');
            }
        });

        Schema::table('cart_item_personalizations', function (Blueprint $table) {
            if (Schema::hasColumn('cart_item_personalizations', 'cart_promotion_item_id')) {
                $table->dropIndex('cip_cart_item_promo_item_idx');
                $table->dropConstrainedForeignId('cart_promotion_item_id');
            }
        });
    }
};
