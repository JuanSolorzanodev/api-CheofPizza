<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OperatorOrderQueryTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    /**
     * @var array<string, OrderStatus>
     */
    private array $statuses = [];

    /**
     * @var array<string, DeliveryType>
     */
    private array $deliveryTypes = [];

    /**
     * @var array<string, PaymentMethod>
     */
    private array $paymentMethods = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = User::factory()
            ->operator()
            ->create();

        foreach (
            [
                'pending',
                'confirmed',
                'preparing',
                'ready',
                'on_the_way',
                'delivered',
                'cancelled',
            ] as $statusName
        ) {
            $this->statuses[$statusName] =
                OrderStatus::query()
                    ->where(
                        'status_name',
                        $statusName,
                    )
                    ->firstOrFail();
        }

        foreach (
            [
                'delivery',
                'pickup',
            ] as $deliveryTypeName
        ) {
            $this->deliveryTypes[$deliveryTypeName] =
                DeliveryType::query()
                    ->where(
                        'delivery_type_name',
                        $deliveryTypeName,
                    )
                    ->firstOrFail();
        }

        foreach (
            [
                'cash',
                'transfer',
                'card',
            ] as $paymentMethodName
        ) {
            $this->paymentMethods[$paymentMethodName] =
                PaymentMethod::query()
                    ->where(
                        'name',
                        $paymentMethodName,
                    )
                    ->firstOrFail();
        }
    }

    public function test_operator_can_list_orders(): void
    {
        $firstOrder = $this->createOrder(
            orderNumber: 'ORD-0001',
            orderedAt: CarbonImmutable::parse(
                '2026-08-01 10:00:00',
            ),
        );

        $secondOrder = $this->createOrder(
            orderNumber: 'ORD-0002',
            orderedAt: CarbonImmutable::parse(
                '2026-08-02 10:00:00',
            ),
        );

        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->getJson(
                '/api/v1/operator/orders',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                2,
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $secondOrder->id,
            )
            ->assertJsonPath(
                'data.0.order_number',
                'ORD-0002',
            )
            ->assertJsonPath(
                'data.1.id',
                (int) $firstOrder->id,
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'order_number',
                        'ordered_at',
                        'total',
                        'status',
                        'delivery_type',
                        'payment_method',
                        'customer' => [
                            'name',
                            'phone',
                        ],
                        'kitchen_summary',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_operator_can_search_order_by_order_number(): void
    {
        $matchingOrder = $this->createOrder(
            orderNumber: 'CHP-SEARCH-001',
        );

        $this->createOrder(
            orderNumber: 'CHP-OTHER-002',
        );

        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->getJson(
                '/api/v1/operator/orders?q=SEARCH-001',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                1,
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $matchingOrder->id,
            )
            ->assertJsonPath(
                'data.0.order_number',
                'CHP-SEARCH-001',
            );
    }

    public function test_operator_can_filter_orders_by_status(): void
    {
        $confirmedOrder = $this->createOrder(
            orderNumber: 'ORD-CONFIRMED',
            status: 'confirmed',
        );

        $this->createOrder(
            orderNumber: 'ORD-PENDING',
            status: 'pending',
        );

        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->getJson(
                '/api/v1/operator/orders?status=confirmed',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                1,
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $confirmedOrder->id,
            )
            ->assertJsonPath(
                'data.0.status',
                'confirmed',
            );
    }

    public function test_operator_can_filter_orders_by_delivery_type(): void
    {
        $deliveryOrder = $this->createOrder(
            orderNumber: 'ORD-DELIVERY',
            deliveryType: 'delivery',
        );

        $this->createOrder(
            orderNumber: 'ORD-PICKUP',
            deliveryType: 'pickup',
        );

        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->getJson(
                '/api/v1/operator/orders?delivery_type=delivery',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                1,
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $deliveryOrder->id,
            )
            ->assertJsonPath(
                'data.0.delivery_type',
                'delivery',
            );
    }

    public function test_operator_can_filter_orders_by_payment_method(): void
    {
        $transferOrder = $this->createOrder(
            orderNumber: 'ORD-TRANSFER',
            paymentMethod: 'transfer',
        );

        $this->createOrder(
            orderNumber: 'ORD-CASH',
            paymentMethod: 'cash',
        );

        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->getJson(
                '/api/v1/operator/orders?payment_method=transfer',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                1,
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $transferOrder->id,
            )
            ->assertJsonPath(
                'data.0.payment_method',
                'transfer',
            );
    }

    public function test_operator_can_filter_orders_by_date_range(): void
    {
        $orderInsideRange = $this->createOrder(
            orderNumber: 'ORD-IN-RANGE',
            orderedAt: CarbonImmutable::parse(
                '2026-08-03 12:00:00',
            ),
        );

        $this->createOrder(
            orderNumber: 'ORD-BEFORE-RANGE',
            orderedAt: CarbonImmutable::parse(
                '2026-07-31 12:00:00',
            ),
        );

        $this->createOrder(
            orderNumber: 'ORD-AFTER-RANGE',
            orderedAt: CarbonImmutable::parse(
                '2026-08-06 12:00:00',
            ),
        );

        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->getJson(
                '/api/v1/operator/orders'
                .'?date_from=2026-08-01'
                .'&date_to=2026-08-05',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                1,
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $orderInsideRange->id,
            );
    }

    public function test_operator_can_paginate_orders(): void
    {
        foreach (range(1, 7) as $index) {
            $this->createOrder(
                orderNumber: sprintf(
                    'ORD-PAGE-%02d',
                    $index,
                ),
                orderedAt: CarbonImmutable::parse(
                    '2026-08-01 10:00:00',
                )->addMinutes($index),
            );
        }

        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->getJson(
                '/api/v1/operator/orders?per_page=5&page=2',
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                2,
                'data',
            )
            ->assertJsonPath(
                'meta.current_page',
                2,
            )
            ->assertJsonPath(
                'meta.last_page',
                2,
            )
            ->assertJsonPath(
                'meta.per_page',
                5,
            )
            ->assertJsonPath(
                'meta.total',
                7,
            );
    }

    public function test_operator_can_view_order_detail(): void
    {
        $order = $this->createOrder(
            orderNumber: 'ORD-DETAIL-001',
            status: 'pending',
            deliveryType: 'delivery',
            paymentMethod: 'cash',
            address: 'Avenida de prueba 123',
        );

        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->getJson(
                "/api/v1/operator/orders/{$order->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                (int) $order->id,
            )
            ->assertJsonPath(
                'data.order_number',
                'ORD-DETAIL-001',
            )
            ->assertJsonPath(
                'data.status',
                'pending',
            )
            ->assertJsonPath(
                'data.allowed_transitions',
                [
                    'confirmed',
                    'cancelled',
                ],
            )
            ->assertJsonPath(
                'data.delivery_type',
                'delivery',
            )
            ->assertJsonPath(
                'data.payment_method',
                'cash',
            )
            ->assertJsonPath(
                'data.delivery.address',
                'Avenida de prueba 123',
            )
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'order_number',
                    'ordered_at',
                    'total',
                    'status',
                    'allowed_transitions',
                    'delivery_type',
                    'payment_method',
                    'customer',
                    'delivery',
                    'customer_confirmation_whatsapp_url',
                    'delivery_whatsapp_url',
                    'payment_receipt',
                    'kitchen' => [
                        'items',
                    ],
                    'status_changes',
                ],
            ]);
    }

    public function test_operator_queue_returns_counts_grouped_by_status(): void
    {
        $this->createOrder(
            orderNumber: 'ORD-PENDING-1',
            status: 'pending',
        );

        $this->createOrder(
            orderNumber: 'ORD-PENDING-2',
            status: 'pending',
        );

        $this->createOrder(
            orderNumber: 'ORD-READY-1',
            status: 'ready',
        );

        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->getJson(
                '/api/v1/operator/orders/queue',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.pending',
                2,
            )
            ->assertJsonPath(
                'data.ready',
                1,
            )
            ->assertJsonPath(
                'data.confirmed',
                0,
            )
            ->assertJsonPath(
                'data.preparing',
                0,
            )
            ->assertJsonPath(
                'data.on_the_way',
                0,
            )
            ->assertJsonPath(
                'data.delivered',
                0,
            )
            ->assertJsonPath(
                'data.cancelled',
                0,
            );
    }

    public function test_operator_can_retrieve_order_status_catalog(): void
    {
        $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->getJson(
                '/api/v1/operator/orders/statuses',
            )
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'pending',
                    'confirmed',
                    'preparing',
                    'ready',
                    'on_the_way',
                    'delivered',
                    'cancelled',
                ],
            ]);
    }

    public function test_operator_order_filters_are_validated(): void
    {
        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->getJson(
                '/api/v1/operator/orders'
                .'?status=unknown'
                .'&delivery_type=drone'
                .'&payment_method=bitcoin'
                .'&per_page=101',
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'delivery_type',
                'payment_method',
                'per_page',
            ]);
    }

    public function test_customer_cannot_access_operator_order_queries(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->getJson(
                '/api/v1/operator/orders',
            )
            ->assertForbidden();

        $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->getJson(
                '/api/v1/operator/orders/queue',
            )
            ->assertForbidden();

        $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->getJson(
                '/api/v1/operator/orders/statuses',
            )
            ->assertForbidden();
    }

    private function createOrder(
        string $orderNumber,
        string $status = 'pending',
        string $deliveryType = 'pickup',
        string $paymentMethod = 'cash',
        ?CarbonImmutable $orderedAt = null,
        ?string $address = null,
    ): Order {
        $customer = User::factory()
            ->customer()
            ->create();

        return Order::query()->create([
            'order_number' => $orderNumber,

            'user_id' => $customer->id,

            'ordered_at' => $orderedAt
                ?? CarbonImmutable::now(),

            'subtotal' => 15.50,

            'delivery_fee' => $deliveryType === 'delivery'
                ? 2.00
                : 0.00,

            'total' => $deliveryType === 'delivery'
                ? 17.50
                : 15.50,

            'delivery_type_id' => $this
                ->deliveryTypes[$deliveryType]
                ->id,

            'address' => $address
                ?? (
                    $deliveryType === 'delivery'
                        ? 'Dirección de prueba'
                        : null
                ),

            'payment_method_id' => $this
                ->paymentMethods[$paymentMethod]
                ->id,

            'order_status_id' => $this
                ->statuses[$status]
                ->id,
        ]);
    }
}
