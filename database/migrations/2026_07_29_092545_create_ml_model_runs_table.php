<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_model_runs', function (Blueprint $table): void {
            $table->id();

            $table->uuid('uuid')->unique();

            /*
             * Evita importar dos veces exactamente el mismo JSON.
             */
            $table->char('source_hash', 64)->unique();

            $table->string('source', 50)->default('google_colab');
            $table->string('status', 30)->default('completed');

            /*
             * Modelo seleccionado para demanda total.
             */
            $table->string('algorithm', 100);
            $table->string('target', 80)->default('total_units');
            $table->string('version', 80);

            $table->date('trained_from');
            $table->date('trained_until');
            $table->unsignedInteger('training_records');

            $table->unsignedSmallInteger('forecast_days');
            $table->date('forecast_from');
            $table->date('forecast_until');

            /*
             * Métricas de prueba.
             */
            $table->decimal('selection_score', 12, 4)->nullable();
            $table->decimal('mae', 12, 4)->nullable();
            $table->decimal('rmse', 12, 4)->nullable();
            $table->decimal('smape', 12, 4)->nullable();
            $table->decimal('r2', 12, 6)->nullable();

            /*
             * Métricas de validación temporal.
             */
            $table->decimal('cv_mae', 12, 4)->nullable();
            $table->decimal('cv_rmse', 12, 4)->nullable();

            $table->timestamp('generated_at')->nullable();
            $table->timestamp('activated_at')->nullable();

            $table->boolean('is_active')->default(false);

            /*
             * Guarda resultados de todos los objetivos:
             * mini, small, medium, family y total_units.
             */
            $table->json('models');

            $table->json('summary')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('metadata')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(
                ['target', 'is_active'],
                'ml_model_runs_target_active_index'
            );

            $table->index(
                ['status', 'generated_at'],
                'ml_model_runs_status_generated_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_model_runs');
    }
};
