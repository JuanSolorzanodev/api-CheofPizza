<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentReceiptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'order_id',
        'user_id',
        'disk',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'status',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'expires_at',
        'file_deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'order_id' =>
                'integer',

            'user_id' =>
                'integer',

            'file_size' =>
                'integer',

            'status' =>
                PaymentReceiptStatus::class,

            'submitted_at' =>
                'datetime',

            'reviewed_at' =>
                'datetime',

            'reviewed_by' =>
                'integer',

            'expires_at' =>
                'datetime',

            'file_deleted_at' =>
                'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            Order::class,
            'order_id',
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id',
        );
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by',
        );
    }

    public function hasStoredFile(): bool
    {
        return $this->file_path !== null
            && $this->file_deleted_at === null;
    }
}
