<?php

declare(strict_types=1);

namespace App\Data\Admin\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Representa un rango de fechas para las consultas administrativas.
 *
 * Las fechas se interpretan en la zona horaria del negocio y se convierten
 * a UTC antes de consultar los timestamps almacenados en la base de datos.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class AnalyticsDateRangeData implements
    Arrayable,
    JsonSerializable
{
    private const DEFAULT_TIMEZONE = 'America/Guayaquil';

    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $timezone,
    ) {
    }

    /**
     * Construye el DTO usando exclusivamente datos previamente validados.
     *
     * Cuando no se envían fechas, se utiliza el día actual completo en la
     * zona horaria de Cheof Pizza.
     *
     * @param array{
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     timezone?: string|null
     * } $data
     */
    public static function fromValidated(array $data): self
    {
        $timezone = filled($data['timezone'] ?? null)
            ? (string) $data['timezone']
            : self::DEFAULT_TIMEZONE;

        $today = CarbonImmutable::now($timezone);

        $dateFrom = (string) (
            $data['date_from']
            ?? $today->toDateString()
        );

        $dateTo = (string) (
            $data['date_to']
            ?? $today->toDateString()
        );

        $from = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $dateFrom,
            $timezone,
        );

        $to = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $dateTo,
            $timezone,
        );

        if ($from === false || $to === false) {
            throw new InvalidArgumentException(
                'No fue posible construir el rango de fechas.'
            );
        }

        return new self(
            from: $from->startOfDay(),
            to: $to->endOfDay(),
            timezone: $timezone,
        );
    }

    /**
     * Inicio del periodo convertido a UTC.
     */
    public function fromUtc(): CarbonImmutable
    {
        return $this->from->utc();
    }

    /**
     * Final del periodo convertido a UTC.
     */
    public function toUtc(): CarbonImmutable
    {
        return $this->to->utc();
    }

    /**
     * Cantidad de días calendario incluidos en el rango.
     *
     * Carbon 3 devuelve float en diffInDays(), por eso se realiza
     * una conversión explícita a entero.
     */
    public function days(): int
    {
        $difference = $this->from
            ->startOfDay()
            ->diffInDays(
                $this->to->startOfDay(),
                absolute: true,
            );

        return (int) $difference + 1;
    }

    /**
     * Construye el periodo inmediatamente anterior con la misma duración.
     *
     * Ejemplo:
     *
     * Periodo actual: 01 al 07.
     * Periodo anterior: 25 al 31.
     */
    public function previous(): self
    {
        $days = $this->days();

        $previousTo = $this->from
            ->subDay()
            ->endOfDay();

        $previousFrom = $previousTo
            ->subDays($days - 1)
            ->startOfDay();

        return new self(
            from: $previousFrom,
            to: $previousTo,
            timezone: $this->timezone,
        );
    }

    /**
     * @return array{
     *     date_from: string,
     *     date_to: string,
     *     timezone: string,
     *     days: int
     * }
     */
    public function toArray(): array
    {
        return [
            'date_from' => $this->from->toDateString(),
            'date_to' => $this->to->toDateString(),
            'timezone' => $this->timezone,
            'days' => $this->days(),
        ];
    }

    /**
     * @return array{
     *     date_from: string,
     *     date_to: string,
     *     timezone: string,
     *     days: int
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
