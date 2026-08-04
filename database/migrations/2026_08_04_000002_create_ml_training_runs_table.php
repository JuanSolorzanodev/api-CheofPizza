<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'ml_training_runs',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->uuid('uuid')
                    ->unique();

                /*
                 * Identifica el dataset exacto utilizado.
                 * No es unique porque se permite reentrenar
                 * voluntariamente con los mismos datos.
                 */
                $table
                    ->char('dataset_hash', 64)
                    ->index();

                $table
                    ->string('status', 30)
                    ->default('processing');

                $table
                    ->string('schema_version', 20)
                    ->default('1.0');

                /*
                 * Identidad del artefacto generado en FastAPI.
                 */
                $table
                    ->string('artifact_id', 150)
                    ->nullable()
                    ->unique();

                $table
                    ->string('version', 100)
                    ->nullable();

                $table
                    ->string('algorithm', 100)
                    ->nullable();

                $table
                    ->string('algorithm_label', 150)
                    ->nullable();

                /*
                 * Periodo y volumen de entrenamiento.
                 */
                $table
                    ->date('trained_from')
                    ->nullable();

                $table
                    ->date('trained_until')
                    ->nullable();

                $table
                    ->unsignedInteger('received_records')
                    ->default(0);

                $table
                    ->unsignedInteger('training_records')
                    ->default(0);

                /*
                 * Métricas globales del candidato ganador.
                 */
                $table
                    ->decimal('mean_mae', 14, 4)
                    ->nullable();

                $table
                    ->decimal('mean_rmse', 14, 4)
                    ->nullable();

                /*
                 * Estado del artefacto respecto al registro
                 * persistente de FastAPI.
                 */
                $table
                    ->boolean('is_active')
                    ->default(false);

                $table
                    ->timestamp('built_at')
                    ->nullable();

                $table
                    ->timestamp('activated_at')
                    ->nullable();

                $table
                    ->timestamp('rolled_back_at')
                    ->nullable();

                $table
                    ->timestamp('failed_at')
                    ->nullable();

                /*
                 * Contrato y resultados completos.
                 */
                $table
                    ->json('request_options')
                    ->nullable();

                $table
                    ->json('dataset_summary')
                    ->nullable();

                $table
                    ->json('targets')
                    ->nullable();

                $table
                    ->json('derived_targets')
                    ->nullable();

                $table
                    ->json('features')
                    ->nullable();

                $table
                    ->json('metrics')
                    ->nullable();

                $table
                    ->json('warnings')
                    ->nullable();

                $table
                    ->json('remote_response')
                    ->nullable();

                $table
                    ->text('error_message')
                    ->nullable();

                $table
                    ->unsignedSmallInteger('remote_status')
                    ->nullable();

                $table
                    ->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->index(
                    [
                        'status',
                        'created_at',
                    ],
                    'ml_training_runs_status_created_index',
                );

                $table->index(
                    [
                        'is_active',
                        'activated_at',
                    ],
                    'ml_training_runs_active_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'ml_training_runs',
        );
    }
};
