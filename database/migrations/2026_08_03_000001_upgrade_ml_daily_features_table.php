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
        /*
         * La tabla anterior no tenía restricción única por fecha.
         * Antes de crearla, conservamos únicamente el registro más reciente
         * de cada día para evitar que una base existente falle al migrar.
         */
        $duplicatedDates = DB::table('ml_daily_features')
            ->select('date')
            ->groupBy('date')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('date');

        foreach ($duplicatedDates as $date) {
            $latestId = DB::table('ml_daily_features')
                ->where('date', $date)
                ->max('id');

            DB::table('ml_daily_features')
                ->where('date', $date)
                ->where('id', '<>', $latestId)
                ->delete();
        }

        Schema::table(
            'ml_daily_features',
            function (Blueprint $table): void {
                /*
                 * El modelo predictivo actual utiliza Personal como "mini".
                 * La tabla anterior no guardaba ese tamaño.
                 */
                $table
                    ->unsignedInteger('mini_sales')
                    ->default(0)
                    ->after('total_pizzas_sold');

                /*
                 * Variables operativas que permiten auditar de dónde
                 * proviene el dato utilizado por Machine Learning.
                 */
                $table
                    ->unsignedInteger('delivered_orders')
                    ->default(0)
                    ->after('regular_sales');

                $table
                    ->unsignedInteger('cancelled_orders')
                    ->default(0)
                    ->after('delivered_orders');

                $table
                    ->decimal('net_sales', 12, 2)
                    ->default(0)
                    ->after('cancelled_orders');

                $table
                    ->unsignedInteger('pickup_orders')
                    ->default(0)
                    ->after('net_sales');

                $table
                    ->unsignedInteger('delivery_orders')
                    ->default(0)
                    ->after('pickup_orders');

                /*
                 * Indica cuándo fue calculada la fila y evita confundir
                 * registros históricos con consolidaciones recientes.
                 */
                $table
                    ->timestamp('consolidated_at')
                    ->nullable()
                    ->after('delivery_orders');

                $table
                    ->string('source', 30)
                    ->default('laravel_sales')
                    ->after('consolidated_at');

                $table->unique(
                    'date',
                    'ml_daily_features_date_unique',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::table(
            'ml_daily_features',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'ml_daily_features_date_unique',
                );

                $table->dropColumn([
                    'mini_sales',
                    'delivered_orders',
                    'cancelled_orders',
                    'net_sales',
                    'pickup_orders',
                    'delivery_orders',
                    'consolidated_at',
                    'source',
                ]);
            },
        );
    }
};
