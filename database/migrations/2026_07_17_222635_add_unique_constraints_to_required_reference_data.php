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
            'cart_statuses',
            function (Blueprint $table): void {
                $table->unique(
                    'status_name',
                    'cart_statuses_status_name_unique',
                );
            },
        );

        Schema::table(
            'order_statuses',
            function (Blueprint $table): void {
                $table->unique(
                    'status_name',
                    'order_statuses_status_name_unique',
                );
            },
        );

        Schema::table(
            'delivery_types',
            function (Blueprint $table): void {
                $table->unique(
                    'delivery_type_name',
                    'delivery_types_delivery_type_name_unique',
                );
            },
        );

        Schema::table(
            'personalization_actions',
            function (Blueprint $table): void {
                $table->unique(
                    'action_name',
                    'personalization_actions_action_name_unique',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::table(
            'cart_statuses',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'cart_statuses_status_name_unique',
                );
            },
        );

        Schema::table(
            'order_statuses',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'order_statuses_status_name_unique',
                );
            },
        );

        Schema::table(
            'delivery_types',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'delivery_types_delivery_type_name_unique',
                );
            },
        );

        Schema::table(
            'personalization_actions',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'personalization_actions_action_name_unique',
                );
            },
        );
    }
};
