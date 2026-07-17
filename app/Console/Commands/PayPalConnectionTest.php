<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payments\PayPal\PayPalClient;
use Illuminate\Console\Command;
use Throwable;

final class PayPalConnectionTest extends Command
{
    protected $signature = 'paypal:test-connection';

    protected $description =
        'Comprueba la autenticación OAuth con PayPal';

    public function handle(
        PayPalClient $payPalClient
    ): int {
        $this->components->info(
            'Comprobando conexión con PayPal...'
        );

        try {
            $payload =
                $payPalClient->getAccessTokenResponse();

            $tokenType = $payload['token_type'] ?? null;
            $expiresIn = $payload['expires_in'] ?? null;
            $appId = $payload['app_id'] ?? null;

            $this->newLine();

            $this->components->twoColumnDetail(
                'Entorno',
                (string) config('paypal.mode')
            );

            $this->components->twoColumnDetail(
                'Token type',
                is_scalar($tokenType)
                    ? (string) $tokenType
                    : 'N/D'
            );

            $this->components->twoColumnDetail(
                'Expires in',
                is_scalar($expiresIn)
                    ? ((string) $expiresIn).' segundos'
                    : 'N/D'
            );

            $this->components->twoColumnDetail(
                'App ID',
                is_scalar($appId)
                    ? (string) $appId
                    : 'N/D'
            );

            $this->newLine();

            $this->components->info(
                'Conexión OAuth con PayPal exitosa.'
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);

            $this->components->error(
                'No fue posible autenticar con PayPal.'
            );

            $this->line(
                $exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}
