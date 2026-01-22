<?php

namespace App\Services\Payments;

use App\Models\BankAccount;

class TransferAccountService
{
    public function getActivePrimary(): ?BankAccount
    {
        return BankAccount::query()
            ->where('active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->first();
    }
}
