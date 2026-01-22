<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
     protected $fillable = [
        'active',
        'priority',
        'bank_name',
        'account_type',
        'account_number',
        'holder_name',
        'holder_id',
        'qr_image_url',
        'instructions',
    ];

        protected $casts = [
        'active' => 'boolean',
        'priority' => 'integer',
    ];
}
