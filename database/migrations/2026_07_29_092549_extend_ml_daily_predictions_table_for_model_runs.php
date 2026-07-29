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
            'ml_daily_predictions',
            function (Blueprint $table): void {
                $table->foreignId('ml_model_run_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('ml_model_runs')
                    ->cascadeOnDelete();

                /*
                 * El dataset histórico utiliza mini, no giant.
                 * giant_pizzas se conserva por compatibilidad.
                 */
                $table->unsignedInteger('mini_pizzas')
                    ->default(0)
                    ->after('total_pizzas');

                $table->string('day_of_week', 30)
                    ->nullable()
                    ->after('prediction_date');

                /*
                 * Se dejan preparados para una fase futura donde
                 * el modelo calcule intervalos de predicción.
                 */
                $table->decimal('lower_bound', 10, 2)
                    ->nullable()
                    ->after('estimated_regular');

                $table->decimal('upper_bound', 10, 2)
                    ->nullable()
                    ->after('lower_bound');

                $table->decimal('confidence_score', 5, 4)
                    ->nullable()
                    ->after('upper_bound');

                $table->json('metadata')
                    ->nullable()
                    ->after('confidence_score');

                $table->unique(
                    [
                        'ml_model_run_id',
                        'prediction_date',
                    ],
                    'ml_daily_predictions_run_date_unique'
                );

                $table->index(
                    'prediction_date',
                    'ml_daily_predictions_date_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'ml_daily_predictions',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'ml_daily_predictions_run_date_unique'
                );

                $table->dropIndex(
                    'ml_daily_predictions_date_index'
                );

                $table->dropForeign([
                    'ml_model_run_id',
                ]);

                $table->dropColumn([
                    'ml_model_run_id',
                    'mini_pizzas',
                    'day_of_week',
                    'lower_bound',
                    'upper_bound',
                    'confidence_score',
                    'metadata',
                ]);
            }
        );
    }
};
