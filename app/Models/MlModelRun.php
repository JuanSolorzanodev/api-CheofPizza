<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MlModelRun extends Model
{
    use HasFactory;

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const SOURCE_GOOGLE_COLAB = 'google_colab';

    public const TARGET_TOTAL_UNITS = 'total_units';

    protected $fillable = [
        'uuid',
        'source_hash',
        'source',
        'status',
        'algorithm',
        'target',
        'version',
        'trained_from',
        'trained_until',
        'training_records',
        'forecast_days',
        'forecast_from',
        'forecast_until',
        'selection_score',
        'mae',
        'rmse',
        'smape',
        'r2',
        'cv_mae',
        'cv_rmse',
        'generated_at',
        'activated_at',
        'is_active',
        'models',
        'summary',
        'recommendations',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'trained_from' => 'date',
        'trained_until' => 'date',
        'forecast_from' => 'date',
        'forecast_until' => 'date',

        'training_records' => 'integer',
        'forecast_days' => 'integer',

        'selection_score' => 'decimal:4',
        'mae' => 'decimal:4',
        'rmse' => 'decimal:4',
        'smape' => 'decimal:4',
        'r2' => 'decimal:6',
        'cv_mae' => 'decimal:4',
        'cv_rmse' => 'decimal:4',

        'generated_at' => 'datetime',
        'activated_at' => 'datetime',
        'is_active' => 'boolean',

        'models' => 'array',
        'summary' => 'array',
        'recommendations' => 'array',
        'metadata' => 'array',
    ];

    public function predictions(): HasMany
    {
        return $this->hasMany(
            MlDailyPrediction::class,
            'ml_model_run_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}
