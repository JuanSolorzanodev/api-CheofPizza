<?php

declare(strict_types=1);

namespace App\Services\MachineLearning;

use App\Models\MlModelRun;
use Illuminate\Pagination\LengthAwarePaginator;

final class MachineLearningRunQueryService
{
    public function findLatestActive(): ?MlModelRun
    {
        return MlModelRun::query()
            ->with([
                'predictions' => static fn ($query) => $query
                    ->orderBy('prediction_date'),

                'creator.role',
            ])
            ->where(
                'status',
                MlModelRun::STATUS_COMPLETED,
            )
            ->where('is_active', true)
            ->latest('generated_at')
            ->first();
    }

    /**
     * @return LengthAwarePaginator<int, MlModelRun>
     */
    public function paginateHistory(
        int $perPage,
    ): LengthAwarePaginator {
        return MlModelRun::query()
            ->with('creator.role')
            ->latest('generated_at')
            ->paginate(
                perPage: $perPage,
            );
    }

    public function findByUuidOrFail(
        string $uuid,
    ): MlModelRun {
        return MlModelRun::query()
            ->with([
                'predictions' => static fn ($query) => $query
                    ->orderBy('prediction_date'),

                'creator.role',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
