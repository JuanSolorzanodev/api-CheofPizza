<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payments\PaymentReceiptService;
use Illuminate\Console\Command;

final class PrunePaymentReceipts extends Command
{
    protected $signature =
        'payment-receipts:prune';

    protected $description =
        'Elimina archivos de comprobantes cuyo periodo de retención terminó.';

    public function handle(
        PaymentReceiptService $service,
    ): int {
        $deleted =
            $service->pruneExpiredFiles();

        $this->info(
            "Archivos de comprobantes eliminados: {$deleted}",
        );

        return self::SUCCESS;
    }
}
