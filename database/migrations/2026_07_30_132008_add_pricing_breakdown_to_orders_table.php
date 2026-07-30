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
        Schema::table(
            'orders',
            function (Blueprint $table): void {
                $table
                    ->decimal('subtotal', 10, 2)
                    ->default(0)
                    ->after('ordered_at');

                $table
                    ->decimal('delivery_fee', 10, 2)
                    ->default(0)
                    ->after('subtotal');
            },
        );

        /*
         * Los pedidos existentes no tenían desglose.
         * Su total histórico se conserva como subtotal.
         */
        DB::table('orders')->update([
            'subtotal' => DB::raw('total'),
            'delivery_fee' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table(
            'orders',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'subtotal',
                    'delivery_fee',
                ]);
            },
        );
    }
};
