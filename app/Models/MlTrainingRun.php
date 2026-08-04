<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MlTrainingRun extends Model
{
    use HasFactory;

    public const STATUS_PROCESSING = 'processing';
    public const STATUS_BUILT = 'built';
    public const STATUS_ACTIVATED = 'activated';
    public const STATUS_ROLLED_BACK = 'rolled_back';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'dataset_hash',
        'status',
        'schema_version',
        'artifact_id',
        'version',
        'algorithm',
        'algorithm_label',
        'trained_from',
        'trained_until',
        'received_records',
        'training_records',
        'mean_mae',
        'mean_rmse',
        'is_active',
        'built_at',
        'activated_at',
        'rolled_back_at',
        'failed_at',
        'request_options',
        'dataset_summary',
        'targets',
        'derived_targets',
        'features',
        'metrics',
        'warnings',
        'remote_response',
        'error_message',
        'remote_status',
        'created_by',
    ];

    protected $casts = [
        'trained_from' => 'date',
        'trained_until' => 'date',

        'received_records' => 'integer',
        'training_records' => 'integer',

        'mean_mae' => 'decimal:4',
        'mean_rmse' => 'decimal:4',

        'is_active' => 'boolean',

        'built_at' => 'datetime',
        'activated_at' => 'datetime',
        'rolled_back_at' => 'datetime',
        'failed_at' => 'datetime',

        'request_options' => 'array',
        'dataset_summary' => 'array',
        'targets' => 'array',
        'derived_targets' => 'array',
        'features' => 'array',
        'metrics' => 'array',
        'warnings' => 'array',
        'remote_response' => 'array',

        'remote_status' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by',
        );
    }

    public function isProcessing(): bool
    {
        return $this->status
            === self::STATUS_PROCESSING;
    }

    public function isBuilt(): bool
    {
        return $this->status
            === self::STATUS_BUILT;
    }

    public function isActivated(): bool
    {
        return $this->status
            === self::STATUS_ACTIVATED;
    }

    public function isFailed(): bool
    {
        return $this->status
            === self::STATUS_FAILED;
    }
}
