<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CART ITEM PERSONALIZATIONS
        Schema::table('cart_item_personalizations', function (Blueprint $table) {
            $table->enum('applies_to', ['ALL', 'A', 'B'])
                ->default('ALL')
                ->after('personalization_action_id');

            $table->index(['cart_item_id', 'applies_to'], 'idx_cart_item_applies_to');
        });

        // ORDER ITEM PERSONALIZATIONS
        Schema::table('order_item_personalizations', function (Blueprint $table) {
            $table->enum('applies_to', ['ALL', 'A', 'B'])
                ->default('ALL')
                ->after('personalization_action_id');

            $table->index(['order_item_id', 'applies_to'], 'idx_order_item_applies_to');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_personalizations', function (Blueprint $table) {
            $table->dropIndex('idx_order_item_applies_to');
            $table->dropColumn('applies_to');
        });

        Schema::table('cart_item_personalizations', function (Blueprint $table) {
            $table->dropIndex('idx_cart_item_applies_to');
            $table->dropColumn('applies_to');
        });
    }
};
