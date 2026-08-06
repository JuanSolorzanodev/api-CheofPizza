<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicatedOrderNumber = DB::table('orders')
            ->select('order_number')
            ->groupBy('order_number')
            ->havingRaw('COUNT(*) > 1')
            ->value('order_number');

        if ($duplicatedOrderNumber !== null) {
            throw new \RuntimeException(
                sprintf(
                    'No se puede crear el índice único de orders.order_number porque existen pedidos duplicados con el número [%s].',
                    (string) $duplicatedOrderNumber,
                ),
            );
        }

        Schema::table(
            'orders',
            function (Blueprint $table): void {
                $table->unique(
                    'order_number',
                    'orders_order_number_unique',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::table(
            'orders',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'orders_order_number_unique',
                );
            },
        );
    }
};
