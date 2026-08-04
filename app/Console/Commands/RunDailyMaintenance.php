<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class RunDailyMaintenance extends Command
{
    /**
     * @var string
     */
    protected $signature = 'system:daily-maintenance
        {--date=yesterday : Fecha de ventas que se consolidará, YYYY-MM-DD o yesterday}';

    /**
     * @var string
     */
    protected $description = 'Ejecuta las tareas de mantenimiento diario de CheofPizza.';

    public function handle(): int
    {
        $date = trim(
            (string) $this->option('date'),
        );

        if ($date === '') {
            $date = 'yesterday';
        }

        $this->components->info(
            'Iniciando mantenimiento diario de CheofPizza.',
        );

        try {
            $this->executeMaintenanceCommand(
                command: 'ml:aggregate-daily-sales',
                parameters: [
                    '--date' => $date,
                    '--no-interaction' => true,
                ],
                label: 'Consolidación diaria de Machine Learning',
            );

            $this->executeMaintenanceCommand(
                command: 'payment-receipts:prune',
                parameters: [
                    '--no-interaction' => true,
                ],
                label: 'Limpieza de comprobantes vencidos',
            );
        } catch (Throwable $exception) {
            report(
                $exception,
            );

            $this->components->error(
                'El mantenimiento diario no pudo completarse: '
                . $exception->getMessage(),
            );

            return self::FAILURE;
        }

        $this->components->info(
            'Mantenimiento diario completado correctamente.',
        );

        return self::SUCCESS;
    }

    /**
     * Ejecuta un comando Artisan interno y detiene el flujo
     * si la tarea termina con error.
     *
     * @param array<string, bool|string|int> $parameters
     */
    private function executeMaintenanceCommand(
        string $command,
        array $parameters,
        string $label,
    ): void {
        $this->newLine();

        $this->components->task(
            $label,
            function () use (
                $command,
                $parameters,
            ): bool {
                $exitCode = $this->call(
                    $command,
                    $parameters,
                );

                if ($exitCode !== self::SUCCESS) {
                    throw new RuntimeException(
                        sprintf(
                            'El comando "%s" terminó con código %d.',
                            $command,
                            $exitCode,
                        ),
                    );
                }

                return true;
            },
        );
    }
}
