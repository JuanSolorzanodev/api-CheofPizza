<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'bank_name'      => (string) $this->bank_name,
            'account_type'   => (string) $this->account_type,
            'account_number' => (string) $this->account_number,
            'holder_name'    => (string) $this->holder_name,
            'holder_id'      => $this->holder_id ? (string) $this->holder_id : null,
            'qr_image_url'   => $this->qr_image_url ? (string) $this->qr_image_url : null,
            'instructions'   => $this->instructions ? (string) $this->instructions : null,
        ];
    }
}
