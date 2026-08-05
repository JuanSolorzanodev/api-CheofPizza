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
function dailySalesReferences(): array
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
function createDailySalesOrder(
    User $customer,
    array $overrides = [],
): Order {
    $references = dailySalesReferences();

    static $sequence = 1;

    return Order::query()->create(
        array_replace(
            [
                'order_number' => sprintf(
                    'CH-DAILY-%04d',
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
function dailySalesCatalog(): array
{
    $category = Category::query()->create([
        'category_name' => 'Categoría ventas diarias '.fake()->uuid(),
        'description' => null,
    ]);

    $size = Size::query()->create([
        'size_name' => 'Mediana ventas diarias '.fake()->uuid(),
        'portion' => 8,
    ]);

    $pizza = Pizza::query()->create([
        'category_id' => $category->id,
        'pizza_name' => 'Americana diaria '.fake()->uuid(),
        'description' => null,
        'image_url' => null,
        'is_visible' => true,
    ]);

    $secondPizza = Pizza::query()->create([
        'category_id' => $category->id,
        'pizza_name' => 'Pepperoni diaria '.fake()->uuid(),
        'description' => null,
        'image_url' => null,
        'is_visible' => true,
    ]);

    $promotion = Promotion::query()->create([
        'promotion_name' => 'Combo diario '.fake()->uuid(),
        'slug' => 'combo-diario-'.fake()->uuid(),
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
    'requires authentication for daily sales analytics',
    function (): void {
        /** @var TestCase $this */
        $this
            ->getJson(
                '/api/v1/admin/analytics/sales/daily',
            )
            ->assertUnauthorized();
    },
);

it(
    'forbids non administrators from daily sales analytics',
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
                '/api/v1/admin/analytics/sales/daily',
            )
            ->assertForbidden();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/daily',
            )
            ->assertForbidden();
    },
);

it(
    'returns every calendar day including days without activity',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        createDailySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-02 12:00:00',
                'total' => '25.00',
            ],
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/daily'
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-03'
                    .'&timezone=America/Guayaquil',
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Ventas diarias recuperadas correctamente.',
            )
            ->assertJsonPath(
                'data.period.date_from',
                '2026-08-01',
            )
            ->assertJsonPath(
                'data.period.date_to',
                '2026-08-03',
            )
            ->assertJsonPath(
                'data.period.timezone',
                'America/Guayaquil',
            )
            ->assertJsonPath(
                'data.period.days',
                3,
            )
            ->assertJsonCount(
                3,
                'data.days',
            )
            ->assertJsonPath(
                'data.days.0.date',
                '2026-08-01',
            )
            ->assertJsonPath(
                'data.days.0.gross_sales',
                0,
            )
            ->assertJsonPath(
                'data.days.0.delivered_orders',
                0,
            )
            ->assertJsonPath(
                'data.days.1.date',
                '2026-08-02',
            )
            ->assertJsonPath(
                'data.days.1.gross_sales',
                25,
            )
            ->assertJsonPath(
                'data.days.1.delivered_orders',
                1,
            )
            ->assertJsonPath(
                'data.days.2.date',
                '2026-08-03',
            )
            ->assertJsonPath(
                'data.days.2.gross_sales',
                0,
            );
    },
);

it(
    'calculates delivered cancelled totals averages and cancellation rates',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $references = dailySalesReferences();

        createDailySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-01 10:00:00',
                'total' => '20.00',
                'order_status_id' => $references['delivered']->id,
            ],
        );

        createDailySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-01 12:00:00',
                'total' => '30.00',
                'order_status_id' => $references['delivered']->id,
            ],
        );

        createDailySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-01 14:00:00',
                'total' => '40.00',
                'order_status_id' => $references['cancelled']->id,
            ],
        );

        /*
         * Un pedido pendiente no debe formar parte de las ventas
         * terminadas ni de la tasa de cancelación.
         */
        createDailySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-01 16:00:00',
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
                '/api/v1/admin/analytics/sales/daily'
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-01',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.days.0.gross_sales',
                50,
            )
            ->assertJsonPath(
                'data.days.0.refunds',
                0,
            )
            ->assertJsonPath(
                'data.days.0.net_sales',
                50,
            )
            ->assertJsonPath(
                'data.days.0.delivered_orders',
                2,
            )
            ->assertJsonPath(
                'data.days.0.cancelled_orders',
                1,
            )
            ->assertJsonPath(
                'data.days.0.average_ticket',
                25,
            )
            ->assertJsonPath(
                'data.days.0.cancellation_rate',
                33.33,
            )
            ->assertJsonPath(
                'data.totals.gross_sales',
                50,
            )
            ->assertJsonPath(
                'data.totals.net_sales',
                50,
            )
            ->assertJsonPath(
                'data.totals.delivered_orders',
                2,
            )
            ->assertJsonPath(
                'data.totals.cancelled_orders',
                1,
            )
            ->assertJsonPath(
                'data.totals.average_ticket',
                25,
            )
            ->assertJsonPath(
                'data.totals.cancellation_rate',
                33.33,
            );
    },
);

it(
    'counts standalone half and half and promotion pizza units',
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
        ] = dailySalesCatalog();

        $order = createDailySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-01 12:00:00',
                'total' => '70.00',
            ],
        );

        /*
         * Dos pizzas completas.
         */
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

        /*
         * Tres pizzas mitad y mitad. Cada unidad sigue contando
         * como una sola pizza física.
         */
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

        /*
         * Un paquete promocional de cantidad 2 que contiene dos
         * pizzas seleccionadas: representa cuatro pizzas físicas.
         */
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
                '/api/v1/admin/analytics/sales/daily'
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-01',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.days.0.pizzas_sold',
                9,
            )
            ->assertJsonPath(
                'data.days.0.promotions_sold',
                2,
            )
            ->assertJsonPath(
                'data.totals.pizzas_sold',
                9,
            )
            ->assertJsonPath(
                'data.totals.promotions_sold',
                2,
            );
    },
);

it(
    'excludes orders outside the requested range',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        createDailySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-07-31 23:59:59',
                'total' => '100.00',
            ],
        );

        createDailySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-01 12:00:00',
                'total' => '25.00',
            ],
        );

        createDailySalesOrder(
            customer: $customer,
            overrides: [
                'ordered_at' => '2026-08-02 00:00:00',
                'total' => '200.00',
            ],
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/daily'
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-01',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.totals.gross_sales',
                25,
            )
            ->assertJsonPath(
                'data.totals.delivered_orders',
                1,
            );
    },
);

it(
    'validates daily sales date range and timezone',
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
                '/api/v1/admin/analytics/sales/daily'
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
                '/api/v1/admin/analytics/sales/daily'
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
