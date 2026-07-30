<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class WhatsAppSetting extends Model
{
    use HasFactory;

    protected $table =
        'whats_app_settings';

    protected $fillable = [
        'active',
        'phone',
        'receipt_template',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
