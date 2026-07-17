<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\OrderStatusName;
use Illuminate\Validation\ValidationException;

final class OrderStatusTransitionService
{
    public const DELIVERY_TYPE_PICKUP = 'pickup';
    public const DELIVERY_TYPE_DELIVERY = 'delivery';

    /**
     * @return list<OrderStatusName>
     */
    public function allowedTransitions(
        OrderStatusName $currentStatus,
        string $deliveryType,
    ): array {
        if ($currentStatus->isFinal()) {
            return [];
        }

        $normalizedDeliveryType = strtolower(
            trim($deliveryType),
        );

        return match ($currentStatus) {
            OrderStatusName::Pending => [
                OrderStatusName::Confirmed,
                OrderStatusName::Cancelled,
            ],

            OrderStatusName::Confirmed => [
                OrderStatusName::Preparing,
                OrderStatusName::Cancelled,
            ],

            OrderStatusName::Preparing => [
                OrderStatusName::Ready,
                OrderStatusName::Cancelled,
            ],

            OrderStatusName::Ready => match ($normalizedDeliveryType) {
                self::DELIVERY_TYPE_PICKUP => [
                    OrderStatusName::Delivered,
                    OrderStatusName::Cancelled,
                ],

                self::DELIVERY_TYPE_DELIVERY => [
                    OrderStatusName::OnTheWay,
                    OrderStatusName::Cancelled,
                ],

                default => [],
            },

            /*
             * Una orden que salió para entrega ya no se cancela mediante
             * el flujo ordinario. Las incidencias se manejarán después
             * mediante un flujo administrativo separado.
             */
            OrderStatusName::OnTheWay => [
                OrderStatusName::Delivered,
            ],

            OrderStatusName::Delivered,
            OrderStatusName::Cancelled => [],
        };
    }

    /**
     * @return list<string>
     */
    public function allowedTransitionValues(
        OrderStatusName $currentStatus,
        string $deliveryType,
    ): array {
        return array_map(
            static fn (
                OrderStatusName $status,
            ): string => $status->value,
            $this->allowedTransitions(
                currentStatus: $currentStatus,
                deliveryType: $deliveryType,
            ),
        );
    }

    /**
     * @throws ValidationException
     */
    public function assertCanTransition(
        OrderStatusName $currentStatus,
        OrderStatusName $destinationStatus,
        string $deliveryType,
    ): void {
        if ($currentStatus === $destinationStatus) {
            throw ValidationException::withMessages([
                'to_status' => [
                    'La orden ya se encuentra en ese estado.',
                ],
            ]);
        }

        if ($currentStatus->isFinal()) {
            throw ValidationException::withMessages([
                'to_status' => [
                    sprintf(
                        'No puedes modificar una orden en estado final (%s).',
                        $currentStatus->value,
                    ),
                ],
            ]);
        }

        $allowedTransitions = $this->allowedTransitions(
            currentStatus: $currentStatus,
            deliveryType: $deliveryType,
        );

        if (
            !in_array(
                $destinationStatus,
                $allowedTransitions,
                true,
            )
        ) {
            throw ValidationException::withMessages([
                'to_status' => [
                    sprintf(
                        'Transición no permitida: %s → %s.',
                        $currentStatus->value,
                        $destinationStatus->value,
                    ),
                ],
            ]);
        }
    }
}
