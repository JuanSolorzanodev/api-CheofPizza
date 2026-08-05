<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MlDailyFeature;
use App\Services\MachineLearning\Dataset\DailySalesFeatureService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

final class AggregateMlDailySales extends Command
{
    protected $signature = 'ml:aggregate-daily-sales
        {--date= : Fecha específica en formato YYYY-MM-DD}
        {--from= : Fecha inicial para una consolidación histórica}
        {--to= : Fecha final para una consolidación histórica}';

    protected $description =
        'Consolida pedidos reales en las características diarias de Machine Learning.';

    public function handle(
        DailySalesFeatureService $service,
    ): int {
        try {
            $date =
                $this->option('date');

            $from =
                $this->option('from');

            $to =
                $this->option('to');

            if (
                $date !== null
                && (
                    $from !== null
                    || $to !== null
                )
            ) {
                $this->components->error(
                    'No combines --date con --from o --to.',
                );

                return self::INVALID;
            }

            if (
                ($from === null)
                !== ($to === null)
            ) {
                $this->components->error(
                    'Para consolidar un rango debes indicar --from y --to.',
                );

                return self::INVALID;
            }

            if ($from !== null && $to !== null) {
                return $this->aggregateRange(
                    service: $service,
                    from: (string) $from,
                    to: (string) $to,
                );
            }

            $targetDate =
                $date !== null
                    ? CarbonImmutable::parse(
                        (string) $date,
                        config('app.timezone'),
                    )
                    : CarbonImmutable::now(
                        config('app.timezone'),
                    )->subDay();

            $feature =
                $service->aggregate(
                    $targetDate,
                );

            $this->renderFeature(
                $feature,
            );

            $this->components->info(
                sprintf(
                    'Ventas del %s consolidadas correctamente.',
                    $feature->date->toDateString(),
                ),
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);

            $this->components->error(
                $exception->getMessage(),
            );

            return self::FAILURE;
        }
    }

    private function aggregateRange(
        DailySalesFeatureService $service,
        string $from,
        string $to,
    ): int {
        $features =
            $service->aggregateRange(
                from: $from,
                to: $to,
            );

        $this->components->info(
            sprintf(
                '%d días fueron consolidados correctamente.',
                count($features),
            ),
        );

        $this->table(
            [
                'Fecha',
                'Pedidos',
                'Pizzas',
                'Ventas',
            ],
            array_map(
                static fn (
                    MlDailyFeature $feature
                ): array => [
                    $feature->date
                        ->toDateString(),

                    $feature
                        ->delivered_orders,

                    $feature
                        ->total_pizzas_sold,

                    '$'
                    .number_format(
                        (float) $feature
                            ->net_sales,
                        2,
                    ),
                ],
                $features,
            ),
        );

        return self::SUCCESS;
    }

    private function renderFeature(
        MlDailyFeature $feature,
    ): void {
        $this->table(
            [
                'Métrica',
                'Valor',
            ],
            [
                [
                    'Fecha',
                    $feature->date
                        ->toDateString(),
                ],
                [
                    'Pedidos entregados',
                    $feature
                        ->delivered_orders,
                ],
                [
                    'Pedidos cancelados',
                    $feature
                        ->cancelled_orders,
                ],
                [
                    'Pizzas físicas',
                    $feature
                        ->total_pizzas_sold,
                ],
                [
                    'Personal / Mini',
                    $feature
                        ->mini_sales,
                ],
                [
                    'Pequeñas',
                    $feature
                        ->small_sales,
                ],
                [
                    'Medianas',
                    $feature
                        ->medium_sales,
                ],
                [
                    'Familiares',
                    $feature
                        ->family_sales,
                ],
                [
                    'Gigantes',
                    $feature
                        ->giant_sales,
                ],
                [
                    'Promociones',
                    $feature
                        ->promotion_sales,
                ],
                [
                    'Venta neta',
                    '$'
                    .number_format(
                        (float) $feature
                            ->net_sales,
                        2,
                    ),
                ],
            ],
        );
    }
}
