<?php

declare(strict_types=1);

namespace App\Services\MachineLearning;

use App\Models\MlModelRun;
use App\Models\User;

final class RemoteForecastService
{
    public function __construct(
        private readonly MachineLearningClient $client,
        private readonly RemoteForecastPersistenceService $persistenceService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(
        string $startDate,
        int $days,
    ): array {
        return $this->client->predict(
            startDate: $startDate,
            days: $days,
        );
    }

    public function generate(
        string $startDate,
        int $days,
        User $admin,
    ): MlModelRun {
        $forecast = $this->preview(
            startDate: $startDate,
            days: $days,
        );

        $run = $this->persistenceService->persist(
            forecast: $forecast,
            admin: $admin,
        );

        $run->loadMissing([
            'predictions' => static fn ($query) => $query
                ->orderBy('prediction_date'),

            'creator.role',
        ]);

        return $run;
    }
}
