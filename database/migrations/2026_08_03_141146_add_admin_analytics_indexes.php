<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'orders',
            function (
                Blueprint $table
            ): void {
                $table->index(
                    [
                        'order_status_id',
                        'ordered_at',
                    ],
                    'orders_status_ordered_at_index',
                );

                $table->index(
                    [
                        'payment_method_id',
                        'ordered_at',
                    ],
                    'orders_payment_method_date_index',
                );
            },
        );

        Schema::table(
            'order_items',
            function (
                Blueprint $table
            ): void {
                $table->index(
                    [
                        'order_id',
                        'promotion_id',
                    ],
                    'order_items_order_promotion_index',
                );

                $table->index(
                    [
                        'order_id',
                        'pizza_id',
                    ],
                    'order_items_order_pizza_index',
                );

                $table->index(
                    [
                        'order_id',
                        'size_id',
                    ],
                    'order_items_order_size_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::table(
            'order_items',
            function (
                Blueprint $table
            ): void {
                $table->dropIndex(
                    'order_items_order_size_index'
                );

                $table->dropIndex(
                    'order_items_order_pizza_index'
                );

                $table->dropIndex(
                    'order_items_order_promotion_index'
                );
            },
        );

        Schema::table(
            'orders',
            function (
                Blueprint $table
            ): void {
                $table->dropIndex(
                    'orders_payment_method_date_index'
                );

                $table->dropIndex(
                    'orders_status_ordered_at_index'
                );
            },
        );
    }
};
