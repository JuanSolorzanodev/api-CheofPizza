<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppSetting extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'whats_app_settings';

    protected $fillable = [
        'active',
        'phone',
        'receipt_template',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
