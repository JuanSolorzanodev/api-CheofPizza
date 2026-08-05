<?php

declare(strict_types=1);

namespace App\Contracts\MachineLearning;

interface MachineLearningClientContract
{
    /**
     * @return array<string, mixed>
     */
    public function model(): array;

    /**
     * @return array<string, mixed>
     */
    public function registry(): array;

    /**
     * @return array<string, mixed>
     */
    public function predict(
        string $startDate,
        int $days,
    ): array;

    /**
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>
     */
    public function validateTrainingDataset(
        array $dataset,
    ): array;

    /**
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>
     */
    public function previewTraining(
        array $dataset,
    ): array;

    /**
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>
     */
    public function buildTrainingArtifact(
        array $dataset,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function activateModel(
        string $artifactId,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function rollbackModel(): array;
}
