<?php

declare(strict_types=1);

namespace App\Services\MachineLearning;

use App\Models\MlTrainingRun;
use Illuminate\Pagination\LengthAwarePaginator;

final class MachineLearningTrainingRunQueryService
{
    /**
     * @return LengthAwarePaginator<int, MlTrainingRun>
     */
    public function paginate(
        int $perPage,
    ): LengthAwarePaginator {
        return MlTrainingRun::query()
            ->with('creator.role')
            ->latest()
            ->paginate(
                perPage: $perPage,
            );
    }

    public function loadDetails(
        MlTrainingRun $trainingRun,
    ): MlTrainingRun {
        return $trainingRun->loadMissing(
            'creator.role',
        );
    }
}
