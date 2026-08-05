<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\MachineLearning;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ImportForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->role_name === 'admin';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'generated_at' => ['required', 'date'],

            'trained_from' => [
                'required',
                'date_format:Y-m-d',
            ],

            'trained_until' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:trained_from',
            ],

            'historical_days' => [
                'required',
                'integer',
                'min:30',
            ],

            'forecast_days' => [
                'required',
                'integer',
                'min:1',
                'max:31',
            ],

            'models' => [
                'required',
                'array',
            ],

            'models.total_units' => [
                'required',
                'array',
            ],

            'models.*.name' => [
                'required',
                'string',
                'max:100',
            ],

            'models.*.selection_score' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'models.*.test_mae' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'models.*.test_rmse' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'models.*.test_smape' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'models.*.test_r2' => [
                'nullable',
                'numeric',
            ],

            'models.*.cv_mae' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'models.*.cv_rmse' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'summary' => [
                'required',
                'array',
            ],

            'summary.historical_total_units' => [
                'required',
                'integer',
                'min:0',
            ],

            'summary.forecast_total_units' => [
                'required',
                'integer',
                'min:0',
            ],

            'summary.forecast_daily_average' => [
                'required',
                'numeric',
                'min:0',
            ],

            'summary.highest_demand_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'summary.highest_demand_day' => [
                'required',
                'string',
                'max:30',
            ],

            'summary.highest_demand_units' => [
                'required',
                'integer',
                'min:0',
            ],

            'summary.highest_demand_size' => [
                'required',
                Rule::in([
                    'mini',
                    'small',
                    'medium',
                    'family',
                ]),
            ],

            'recommendations' => [
                'required',
                'array',
                'min:1',
            ],

            'recommendations.*' => [
                'required',
                'string',
                'max:1000',
            ],

            'predictions' => [
                'required',
                'array',
                'min:1',
                'max:31',
            ],

            'predictions.*.date' => [
                'required',
                'date_format:Y-m-d',
                'distinct',
            ],

            'predictions.*.mini' => [
                'required',
                'integer',
                'min:0',
            ],

            'predictions.*.small' => [
                'required',
                'integer',
                'min:0',
            ],

            'predictions.*.medium' => [
                'required',
                'integer',
                'min:0',
            ],

            'predictions.*.family' => [
                'required',
                'integer',
                'min:0',
            ],

            'predictions.*.total_units' => [
                'required',
                'integer',
                'min:0',
            ],

            'predictions.*.day_of_week' => [
                'nullable',
                'string',
                'max:30',
            ],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateForecastDays($validator);
                $this->validatePredictionTotals($validator);
                $this->validatePredictionDates($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'models.total_units.required' => 'El JSON no contiene el modelo total_units.',

            'predictions.required' => 'El JSON no contiene predicciones.',

            'predictions.*.date.distinct' => 'El JSON contiene fechas de predicción duplicadas.',
        ];
    }

    private function validateForecastDays(
        Validator $validator
    ): void {
        $predictions = $this->input(
            'predictions',
            []
        );

        if (! is_array($predictions)) {
            return;
        }

        if (
            $this->integer('forecast_days')
            !== count($predictions)
        ) {
            $validator->errors()->add(
                'forecast_days',
                'forecast_days no coincide con la cantidad de predicciones.'
            );
        }
    }

    private function validatePredictionTotals(
        Validator $validator
    ): void {
        $predictions = $this->input(
            'predictions',
            []
        );

        if (! is_array($predictions)) {
            return;
        }

        foreach ($predictions as $index => $prediction) {
            if (! is_array($prediction)) {
                continue;
            }

            $requiredKeys = [
                'mini',
                'small',
                'medium',
                'family',
                'total_units',
            ];

            foreach ($requiredKeys as $key) {
                if (! array_key_exists($key, $prediction)) {
                    continue 2;
                }
            }

            $calculatedTotal =
                (int) $prediction['mini']
                + (int) $prediction['small']
                + (int) $prediction['medium']
                + (int) $prediction['family'];

            if (
                $calculatedTotal
                !== (int) $prediction['total_units']
            ) {
                $validator->errors()->add(
                    "predictions.{$index}.total_units",
                    'El total no coincide con la suma de los tamaños.'
                );
            }
        }
    }

    private function validatePredictionDates(
        Validator $validator
    ): void {
        $trainedUntilValue = $this->input(
            'trained_until'
        );

        $predictions = $this->input(
            'predictions',
            []
        );

        if (
            ! is_string($trainedUntilValue)
            || ! is_array($predictions)
        ) {
            return;
        }

        try {
            $trainedUntil = CarbonImmutable::parse(
                $trainedUntilValue
            )->startOfDay();
        } catch (\Throwable) {
            return;
        }

        foreach ($predictions as $index => $prediction) {
            if (
                ! is_array($prediction)
                || ! isset($prediction['date'])
                || ! is_string($prediction['date'])
            ) {
                continue;
            }

            try {
                $predictionDate = CarbonImmutable::parse(
                    $prediction['date']
                )->startOfDay();
            } catch (\Throwable) {
                continue;
            }

            if (
                $predictionDate->lessThanOrEqualTo(
                    $trainedUntil
                )
            ) {
                $validator->errors()->add(
                    "predictions.{$index}.date",
                    'La fecha pronosticada debe ser posterior al último día de entrenamiento.'
                );
            }
        }
    }
}
