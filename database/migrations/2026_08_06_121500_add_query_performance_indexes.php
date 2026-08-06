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
            function (Blueprint $table): void {
                $table->index(
                    [
                        'user_id',
                        'ordered_at',
                        'id',
                    ],
                    'orders_user_ordered_id_index',
                );
            },
        );

        Schema::table(
            'order_status_changes',
            function (Blueprint $table): void {
                $table->index(
                    [
                        'to_order_status_id',
                        'changed_at',
                        'order_id',
                    ],
                    'order_status_changes_status_changed_order_index',
                );
            },
        );

        Schema::table(
            'payments',
            function (Blueprint $table): void {
                $table->index(
                    [
                        'status',
                        'paid_at',
                    ],
                    'payments_status_paid_at_index',
                );

                $table->index(
                    [
                        'status',
                        'created_at',
                    ],
                    'payments_status_created_at_index',
                );

                $table->index(
                    [
                        'status',
                        'refunded_at',
                    ],
                    'payments_status_refunded_at_index',
                );
            },
        );

        Schema::table(
            'payment_receipts',
            function (Blueprint $table): void {
                $table->index(
                    [
                        'status',
                        'reviewed_at',
                    ],
                    'payment_receipts_status_reviewed_at_index',
                );

                $table->index(
                    [
                        'status',
                        'submitted_at',
                    ],
                    'payment_receipts_status_submitted_at_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::table(
            'payment_receipts',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'payment_receipts_status_submitted_at_index',
                );

                $table->dropIndex(
                    'payment_receipts_status_reviewed_at_index',
                );
            },
        );

        Schema::table(
            'payments',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'payments_status_refunded_at_index',
                );

                $table->dropIndex(
                    'payments_status_created_at_index',
                );

                $table->dropIndex(
                    'payments_status_paid_at_index',
                );
            },
        );

        Schema::table(
            'order_status_changes',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'order_status_changes_status_changed_order_index',
                );
            },
        );

        Schema::table(
            'orders',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'orders_user_ordered_id_index',
                );
            },
        );
    }
};
