<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPromotionItem;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\Pizza;
use App\Models\Promotion;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     delivered: OrderStatus,
 *     cancelled: OrderStatus,
 *     pending: OrderStatus,
 *     delivery: DeliveryType,
 *     cash: PaymentMethod
 * }
 */
function hourlySalesReferences(): array
{
    return [
        'delivered' => OrderStatus::query()->firstOrCreate([
            'status_name' => 'delivered',
        ]),

        'cancelled' => OrderStatus::query()->firstOrCreate([
            'status_name' => 'cancelled',
        ]),

        'pending' => OrderStatus::query()->firstOrCreate([
            'status_name' => 'pending',
        ]),

        'delivery' => DeliveryType::query()->firstOrCreate([
            'delivery_type_name' => 'delivery',
        ]),

        'cash' => PaymentMethod::query()->firstOrCreate(
            [
                'name' => 'cash',
            ],
            [
                'description' => 'Pago en efectivo',
                'active' => true,
            ],
        ),
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createHourlySalesOrder(
    User $customer,
    array $overrides = [],
): Order {
    $references = hourlySalesReferences();

    static $sequence = 1;

    return Order::query()->create(
        array_replace(
            [
                'order_number' => sprintf(
                    'CH-HOURLY-%04d',
                    $sequence++,
                ),

                'user_id' => $customer->id,
                'ordered_at' => '2026-08-01 12:00:00',
                'subtotal' => '20.00',
                'delivery_fee' => '0.00',
                'total' => '20.00',

                'delivery_type_id' => $references[
                    'delivery'
                ]->id,

                'payment_method_id' => $references[
                    'cash'
                ]->id,

                'order_status_id' => $references[
                    'delivered'
                ]->id,

                'address' => null,
                'delivery_lat' => null,
                'delivery_lng' => null,
                'delivery_maps_url' => null,
                'delivery_place_id' => null,
                'delivery_reference' => null,
            ],
            $overrides,
        ),
    );
}

/**
 * @return array{
 *     pizza: Pizza,
 *     second_pizza: Pizza,
 *     size: Size,
 *     promotion: Promotion
 * }
 */
function hourlySalesCatalog(): array
{
    $category = Category::query()->create([
        'category_name' => 'Categoría ventas por hora '.fake()->uuid(),
        'description' => null,
    ]);

    $size = Size::query()->create([
        'size_name' => 'Mediana ventas por hora '.fake()->uuid(),
        'portion' => 8,
    ]);

    $pizza = Pizza::query()->create([
        'category_id' => $category->id,
        'pizza_name' => 'Americana por hora '.fake()->uuid(),
        'description' => null,
        'image_url' => null,
        'is_visible' => true,
    ]);

    $secondPizza = Pizza::query()->create([
        'category_id' => $category->id,
        'pizza_name' => 'Pepperoni por hora '.fake()->uuid(),
        'description' => null,
        'image_url' => null,
        'is_visible' => true,
    ]);

    $promotion = Promotion::query()->create([
        'promotion_name' => 'Combo por hora '.fake()->uuid(),
        'slug' => 'combo-horario-'.fake()->uuid(),
        'description' => null,
        'banner_image_url' => null,
        'promotion_type' => Promotion::TYPE_FIXED_COMBO,
        'selection_quantity' => 2,
        'promotion_price' => '18.00',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
    ]);

    return [
        'pizza' => $pizza,
        'second_pizza' => $secondPizza,
        'size' => $size,
        'promotion' => $promotion,
    ];
}

it(
    'requires authentication for hourly sales analytics',
    function (): void {
        /** @var TestCase $this */
        $this
            ->getJson(
                '/api/v1/admin/analytics/sales/hourly',
            )
            ->assertUnauthorized();
    },
);

it(
    'forbids non administrators from hourly sales analytics',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create();

        $operator = User::factory()
            ->operator()
            ->create();

        $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/hourly',
            )
            ->assertForbidden();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/hourly',
            )
            ->assertForbidden();
    },
);

it(
    'returns all twenty four hours including hours without activity',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        createHourlySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-01 14:30:00',
                'total' => '25.00',
            ],
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/hourly'
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-01'
                    .'&timezone=America/Guayaquil',
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Ventas por hora recuperadas correctamente.',
            )
            ->assertJsonPath(
                'data.period.date_from',
                '2026-08-01',
            )
            ->assertJsonPath(
                'data.period.date_to',
                '2026-08-01',
            )
            ->assertJsonPath(
                'data.period.timezone',
                'America/Guayaquil',
            )
            ->assertJsonCount(
                24,
                'data.hours',
            )
            ->assertJsonPath(
                'data.hours.0.hour',
                0,
            )
            ->assertJsonPath(
                'data.hours.0.label',
                '00:00',
            )
            ->assertJsonPath(
                'data.hours.0.gross_sales',
                0,
            )
            ->assertJsonPath(
                'data.hours.14.hour',
                14,
            )
            ->assertJsonPath(
                'data.hours.14.label',
                '14:00',
            )
            ->assertJsonPath(
                'data.hours.14.gross_sales',
                25,
            )
            ->assertJsonPath(
                'data.hours.14.delivered_orders',
                1,
            )
            ->assertJsonPath(
                'data.hours.23.hour',
                23,
            )
            ->assertJsonPath(
                'data.hours.23.label',
                '23:00',
            );
    },
);

it(
    'calculates hourly delivered cancelled averages and cancellation rates',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $references = hourlySalesReferences();

        createHourlySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-01 10:10:00',
                'total' => '20.00',
                'order_status_id' => $references['delivered']->id,
            ],
        );

        createHourlySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-01 10:40:00',
                'total' => '30.00',
                'order_status_id' => $references['delivered']->id,
            ],
        );

        createHourlySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-01 10:50:00',
                'total' => '40.00',
                'order_status_id' => $references['cancelled']->id,
            ],
        );

        createHourlySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-01 10:55:00',
                'total' => '100.00',
                'order_status_id' => $references['pending']->id,
            ],
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/hourly'
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-01',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.hours.10.gross_sales',
                50,
            )
            ->assertJsonPath(
                'data.hours.10.refunds',
                0,
            )
            ->assertJsonPath(
                'data.hours.10.net_sales',
                50,
            )
            ->assertJsonPath(
                'data.hours.10.delivered_orders',
                2,
            )
            ->assertJsonPath(
                'data.hours.10.cancelled_orders',
                1,
            )
            ->assertJsonPath(
                'data.hours.10.average_ticket',
                25,
            )
            ->assertJsonPath(
                'data.hours.10.cancellation_rate',
                33.33,
            )
            ->assertJsonPath(
                'data.summary.gross_sales',
                50,
            )
            ->assertJsonPath(
                'data.summary.net_sales',
                50,
            )
            ->assertJsonPath(
                'data.summary.delivered_orders',
                2,
            )
            ->assertJsonPath(
                'data.summary.cancelled_orders',
                1,
            )
            ->assertJsonPath(
                'data.summary.average_ticket',
                25,
            );
    },
);

it(
    'detects peak sales and peak order hours',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        createHourlySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-01 11:00:00',
                'total' => '70.00',
            ],
        );

        createHourlySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-01 18:00:00',
                'total' => '40.00',
            ],
        );

        createHourlySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-01 18:30:00',
                'total' => '40.00',
            ],
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/hourly'
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-01',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.peak_sales_hour',
                18,
            )
            ->assertJsonPath(
                'data.summary.peak_sales_hour_label',
                '18:00',
            )
            ->assertJsonPath(
                'data.summary.peak_sales_amount',
                80,
            )
            ->assertJsonPath(
                'data.summary.peak_orders_hour',
                18,
            )
            ->assertJsonPath(
                'data.summary.peak_orders_hour_label',
                '18:00',
            )
            ->assertJsonPath(
                'data.summary.peak_orders_count',
                2,
            );
    },
);

it(
    'counts standalone half and half and promotion pizza units by hour',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        [
            'pizza' => $pizza,
            'second_pizza' => $secondPizza,
            'size' => $size,
            'promotion' => $promotion,
        ] = hourlySalesCatalog();

        $order = createHourlySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-01 19:15:00',
                'total' => '70.00',
            ],
        );

        OrderItem::query()->create([
            'order_id' => $order->id,
            'promotion_id' => null,
            'promotion_name' => null,
            'pizza_id' => $pizza->id,
            'pizza_name' => $pizza->pizza_name,
            'pizza_id_second' => null,
            'pizza_name_second' => null,
            'category_name' => 'Tradicionales',
            'category_name_second' => null,
            'size_id' => $size->id,
            'size_name' => $size->size_name,
            'is_half_and_half' => false,
            'quantity' => 2,
            'unit_price' => '10.00',
            'subtotal' => '20.00',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'promotion_id' => null,
            'promotion_name' => null,
            'pizza_id' => $pizza->id,
            'pizza_name' => $pizza->pizza_name,
            'pizza_id_second' => $secondPizza->id,
            'pizza_name_second' => $secondPizza->pizza_name,
            'category_name' => 'Tradicionales',
            'category_name_second' => 'Tradicionales',
            'size_id' => $size->id,
            'size_name' => $size->size_name,
            'is_half_and_half' => true,
            'quantity' => 3,
            'unit_price' => '10.00',
            'subtotal' => '30.00',
        ]);

        $promotionItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'promotion_id' => $promotion->id,
            'promotion_name' => $promotion->promotion_name,
            'pizza_id' => null,
            'pizza_name' => null,
            'pizza_id_second' => null,
            'pizza_name_second' => null,
            'category_name' => null,
            'category_name_second' => null,
            'size_id' => $size->id,
            'size_name' => $size->size_name,
            'is_half_and_half' => false,
            'quantity' => 2,
            'unit_price' => '10.00',
            'subtotal' => '20.00',
        ]);

        OrderPromotionItem::query()->create([
            'order_item_id' => $promotionItem->id,
            'pizza_id' => $pizza->id,
            'pizza_name' => $pizza->pizza_name,
        ]);

        OrderPromotionItem::query()->create([
            'order_item_id' => $promotionItem->id,
            'pizza_id' => $secondPizza->id,
            'pizza_name' => $secondPizza->pizza_name,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/hourly'
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-01',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.hours.19.pizzas_sold',
                9,
            )
            ->assertJsonPath(
                'data.hours.19.promotions_sold',
                2,
            )
            ->assertJsonPath(
                'data.summary.pizzas_sold',
                9,
            )
            ->assertJsonPath(
                'data.summary.promotions_sold',
                2,
            );
    },
);

it(
    'returns null peak values when there are no delivered orders',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/hourly'
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-01',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.gross_sales',
                0,
            )
            ->assertJsonPath(
                'data.summary.delivered_orders',
                0,
            )
            ->assertJsonPath(
                'data.summary.peak_sales_hour',
                null,
            )
            ->assertJsonPath(
                'data.summary.peak_sales_hour_label',
                null,
            )
            ->assertJsonPath(
                'data.summary.peak_sales_amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.peak_orders_hour',
                null,
            )
            ->assertJsonPath(
                'data.summary.peak_orders_hour_label',
                null,
            )
            ->assertJsonPath(
                'data.summary.peak_orders_count',
                0,
            );
    },
);

it(
    'validates hourly sales date range and timezone',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/hourly'
                    .'?date_from=2026-08-10'
                    .'&date_to=2026-08-01'
                    .'&timezone=America/Bogota',
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
                'timezone',
            ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/hourly'
                    .'?date_from=2025-01-01'
                    .'&date_to=2026-01-02',
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
            ])
            ->assertJsonPath(
                'errors.date_to.0',
                'El rango consultado no puede superar 366 días.',
            );
    },
);
