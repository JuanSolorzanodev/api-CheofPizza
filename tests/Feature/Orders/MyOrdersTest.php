<?php

declare(strict_types=1);

use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\User;
use Tests\TestCase;

/**
 * @return array{
 *     delivery: DeliveryType,
 *     pickup: DeliveryType,
 *     cash: PaymentMethod,
 *     transfer: PaymentMethod,
 *     pending: OrderStatus,
 *     delivered: OrderStatus
 * }
 */
function myOrdersReferenceData(): array
{
    $delivery = DeliveryType::query()->firstOrCreate([
        'delivery_type_name' => 'delivery',
    ]);

    $pickup = DeliveryType::query()->firstOrCreate([
        'delivery_type_name' => 'pickup',
    ]);

    $cash = PaymentMethod::query()->firstOrCreate(
        [
            'name' => 'cash',
        ],
        [
            'description' => 'Pago en efectivo',
            'active' => true,
        ],
    );

    $transfer = PaymentMethod::query()->firstOrCreate(
        [
            'name' => 'transfer',
        ],
        [
            'description' => 'Transferencia bancaria',
            'active' => true,
        ],
    );

    $pending = OrderStatus::query()->firstOrCreate([
        'status_name' => 'pending',
    ]);

    $delivered = OrderStatus::query()->firstOrCreate([
        'status_name' => 'delivered',
    ]);

    return [
        'delivery' => $delivery,
        'pickup' => $pickup,
        'cash' => $cash,
        'transfer' => $transfer,
        'pending' => $pending,
        'delivered' => $delivered,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createMyOrder(
    User $user,
    array $overrides = [],
): Order {
    $references = myOrdersReferenceData();

    static $sequence = 1;

    $defaults = [
        'order_number' => sprintf(
            'CH-MY-%04d',
            $sequence++,
        ),

        'user_id' => $user->id,
        'ordered_at' => now(),
        'subtotal' => '20.00',
        'delivery_fee' => '2.00',
        'total' => '22.00',

        'delivery_type_id' => $references['delivery']->id,
        'address' => 'Av. Principal y Calle 10',
        'delivery_lat' => '-0.8456100',
        'delivery_lng' => '-80.1638900',
        'delivery_maps_url' => 'https://www.google.com/maps?q=-0.84561,-80.16389',
        'delivery_place_id' => 'place-test',
        'delivery_reference' => 'Casa color blanco',

        'payment_method_id' => $references['cash']->id,
        'order_status_id' => $references['pending']->id,
    ];

    return Order::query()->create(
        array_replace(
            $defaults,
            $overrides,
        ),
    );
}

it(
    'requires authentication to list and view customer orders',
    function (): void {
        /** @var TestCase $this */
        $this
            ->getJson('/api/v1/my/orders')
            ->assertUnauthorized();

        $this
            ->getJson('/api/v1/my/orders/1')
            ->assertUnauthorized();
    },
);

it(
    'returns only orders that belong to the authenticated customer',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create();

        $otherCustomer = User::factory()
            ->customer()
            ->create();

        $firstOrder = createMyOrder(
            user: $customer,
            overrides: [
                'order_number' => 'CH-CUSTOMER-001',
                'ordered_at' => '2026-08-05 10:00:00',
            ],
        );

        createMyOrder(
            user: $otherCustomer,
            overrides: [
                'order_number' => 'CH-OTHER-001',
                'ordered_at' => '2026-08-05 11:00:00',
            ],
        );

        $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->getJson('/api/v1/my/orders')
            ->assertOk()
            ->assertJsonCount(
                1,
                'data',
            )
            ->assertJsonPath(
                'data.0.id',
                (int) $firstOrder->id,
            )
            ->assertJsonPath(
                'data.0.order_number',
                'CH-CUSTOMER-001',
            )
            ->assertJsonPath(
                'meta.total',
                1,
            );
    },
);

it(
    'orders customer orders from newest to oldest',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create();

        $olderOrder = createMyOrder(
            user: $customer,
            overrides: [
                'order_number' => 'CH-OLDER-001',
                'ordered_at' => '2026-08-04 10:00:00',
            ],
        );

        $newerOrder = createMyOrder(
            user: $customer,
            overrides: [
                'order_number' => 'CH-NEWER-001',
                'ordered_at' => '2026-08-05 10:00:00',
            ],
        );

        $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->getJson('/api/v1/my/orders')
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                (int) $newerOrder->id,
            )
            ->assertJsonPath(
                'data.1.id',
                (int) $olderOrder->id,
            );
    },
);

it(
    'returns paginated customer orders and limits per page to fifty',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create();

        foreach (range(1, 55) as $number) {
            createMyOrder(
                user: $customer,
                overrides: [
                    'order_number' => sprintf(
                        'CH-PAGE-%03d',
                        $number,
                    ),

                    'ordered_at' => now()
                        ->subMinutes($number),
                ],
            );
        }

        $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->getJson(
                '/api/v1/my/orders?per_page=100&page=1',
            )
            ->assertOk()
            ->assertJsonCount(
                50,
                'data',
            )
            ->assertJsonPath(
                'meta.current_page',
                1,
            )
            ->assertJsonPath(
                'meta.per_page',
                50,
            )
            ->assertJsonPath(
                'meta.total',
                55,
            )
            ->assertJsonPath(
                'meta.from',
                1,
            )
            ->assertJsonPath(
                'meta.to',
                50,
            )
            ->assertJsonPath(
                'meta.last_page',
                2,
            );

        $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->getJson(
                '/api/v1/my/orders?per_page=100&page=2',
            )
            ->assertOk()
            ->assertJsonCount(
                5,
                'data',
            )
            ->assertJsonPath(
                'meta.current_page',
                2,
            )
            ->assertJsonPath(
                'meta.from',
                51,
            )
            ->assertJsonPath(
                'meta.to',
                55,
            );
    },
);

it(
    'returns the safe summary structure in the customer order list',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create();

        $order = createMyOrder(
            user: $customer,
            overrides: [
                'order_number' => 'CH-SUMMARY-001',
                'subtotal' => '25.50',
                'delivery_fee' => '2.75',
                'total' => '28.25',
            ],
        );

        $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->getJson('/api/v1/my/orders')
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                (int) $order->id,
            )
            ->assertJsonPath(
                'data.0.order_number',
                'CH-SUMMARY-001',
            )
            ->assertJsonPath(
                'data.0.subtotal',
                25.5,
            )
            ->assertJsonPath(
                'data.0.delivery_fee',
                2.75,
            )
            ->assertJsonPath(
                'data.0.total',
                28.25,
            )
            ->assertJsonPath(
                'data.0.currency',
                'USD',
            )
            ->assertJsonPath(
                'data.0.delivery_type',
                'delivery',
            )
            ->assertJsonPath(
                'data.0.payment_method',
                'cash',
            )
            ->assertJsonPath(
                'data.0.status',
                'pending',
            )
            ->assertJsonPath(
                'data.0.address',
                'Av. Principal y Calle 10',
            )
            ->assertJsonPath(
                'data.0.delivery_location.lat',
                -0.84561,
            )
            ->assertJsonPath(
                'data.0.delivery_location.lng',
                -80.16389,
            )
            ->assertJsonPath(
                'data.0.delivery_location.place_id',
                'place-test',
            )
            ->assertJsonPath(
                'data.0.delivery_location.reference',
                'Casa color blanco',
            )
            ->assertJsonPath(
                'data.0.items_count',
                0,
            );
    },
);

it(
    'returns the detail of an order that belongs to the authenticated customer',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create([
                'first_name' => 'Jandry',
                'last_name' => 'Zambrano',
                'phone' => '0999999999',
            ]);

        $order = createMyOrder(
            user: $customer,
            overrides: [
                'order_number' => 'CH-DETAIL-001',
            ],
        );

        $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->getJson(
                "/api/v1/my/orders/{$order->id}",
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                (int) $order->id,
            )
            ->assertJsonPath(
                'data.order_number',
                'CH-DETAIL-001',
            )
            ->assertJsonPath(
                'data.customer.id',
                (int) $customer->id,
            )
            ->assertJsonPath(
                'data.customer.name',
                'Zambrano',
            )
            ->assertJsonPath(
                'data.customer.email',
                $customer->email,
            )
            ->assertJsonPath(
                'data.customer.phone',
                '0999999999',
            )
            ->assertJsonPath(
                'data.items',
                [],
            )
            ->assertJsonPath(
                'data.payment_receipt',
                null,
            )
            ->assertJsonPath(
                'data.status_changes',
                [],
            );
    },
);

it(
    'does not expose an order that belongs to another customer',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create();

        $otherCustomer = User::factory()
            ->customer()
            ->create();

        $foreignOrder = createMyOrder(
            user: $otherCustomer,
            overrides: [
                'order_number' => 'CH-FOREIGN-001',
            ],
        );

        $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->getJson(
                "/api/v1/my/orders/{$foreignOrder->id}",
            )
            ->assertNotFound();
    },
);

it(
    'returns not found for an unknown customer order',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create();

        $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->getJson('/api/v1/my/orders/999999')
            ->assertNotFound();
    },
);
