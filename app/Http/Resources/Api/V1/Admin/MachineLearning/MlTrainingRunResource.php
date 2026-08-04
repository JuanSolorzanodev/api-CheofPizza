<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\MachineLearning;

use App\Models\MlTrainingRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MlTrainingRun
 */
final class MlTrainingRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request,
    ): array {
        return [
            'id' =>
                $this->id,

            'uuid' =>
                $this->uuid,

            'status' =>
                $this->status,

            'dataset_hash' =>
                $this->dataset_hash,

            'artifact' => [
                'id' =>
                    $this->artifact_id,

                'version' =>
                    $this->version,

                'algorithm' =>
                    $this->algorithm,

                'algorithm_label' =>
                    $this->algorithm_label,

                'is_active' =>
                    $this->is_active,
            ],

            'training' => [
                'schema_version' =>
                    $this->schema_version,

                'from' =>
                    $this->trained_from
                        ?->toDateString(),

                'until' =>
                    $this->trained_until
                        ?->toDateString(),

                'received_records' =>
                    $this->received_records,

                'usable_records' =>
                    $this->training_records,
            ],

            'metrics' => [
                'mean_mae' =>
                    $this->mean_mae,

                'mean_rmse' =>
                    $this->mean_rmse,

                'targets' =>
                    $this->metrics,
            ],

            'contract' => [
                'targets' =>
                    $this->targets,

                'derived_targets' =>
                    $this->derived_targets,

                'features' =>
                    $this->features,
            ],

            'dataset_summary' =>
                $this->dataset_summary,

            'request_options' =>
                $this->request_options,

            'warnings' =>
                $this->warnings,

            'error' =>
                $this->status
                === MlTrainingRun::STATUS_FAILED
                    ? [
                        'message' =>
                            $this->error_message,

                        'remote_status' =>
                            $this->remote_status,
                    ]
                    : null,

            'timestamps' => [
                'built_at' =>
                    $this->built_at
                        ?->toIso8601String(),

                'activated_at' =>
                    $this->activated_at
                        ?->toIso8601String(),

                'rolled_back_at' =>
                    $this->rolled_back_at
                        ?->toIso8601String(),

                'failed_at' =>
                    $this->failed_at
                        ?->toIso8601String(),

                'created_at' =>
                    $this->created_at
                        ?->toIso8601String(),

                'updated_at' =>
                    $this->updated_at
                        ?->toIso8601String(),
            ],

            'created_by' =>
                $this->whenLoaded(
                    'creator',
                    fn (): ?array =>
                        $this->creator === null
                            ? null
                            : [
                                'id' =>
                                    $this->creator->id,

                                'name' =>
                                    trim(
                                        $this->creator
                                            ->first_name
                                        . ' '
                                        . $this->creator
                                            ->last_name,
                                    ),

                                'email' =>
                                    $this->creator
                                        ->email,
                            ],
                ),
        ];
    }
}
