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
use App\Enums\PaymentReceiptStatus;
use App\Enums\PaymentStatus;
use App\Models\OrderStatusChange;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Crea el catálogo mínimo requerido por las pruebas del dashboard.
 *
 * Todos los registros respetan los campos obligatorios definidos
 * actualmente por las migraciones reales del proyecto.
 *
 * @return array{
 *     delivered_status_id: int,
 *     cancelled_status_id: int,
 *     delivery_type_id: int,
 *     payment_method_id: int,
 *     size_id: int,
 *     category_id: int,
 *     pizza_id: int,
 *     promotion_id: int
 * }
 */
function createSalesDashboardCatalog(): array
{
    $deliveredStatus = OrderStatus::query()
        ->firstOrCreate([
            'status_name' => 'delivered',
        ]);

    $cancelledStatus = OrderStatus::query()
        ->firstOrCreate([
            'status_name' => 'cancelled',
        ]);

    $deliveryType = DeliveryType::query()
        ->firstOrCreate([
            'delivery_type_name' => 'pickup',
        ]);

    $paymentMethod = PaymentMethod::query()
        ->firstOrCreate(
            [
                'name' => 'cash',
            ],
            [
                'description' =>
                'Pago en efectivo para pruebas del dashboard',

                'active' => true,
            ],
        );

    /*
     * La columna sizes.portion no tiene valor por defecto,
     * por eso debe enviarse obligatoriamente.
     */
    $size = Size::query()->create([
        'size_name' =>
        'Mediana dashboard',

        'portion' =>
        8,
    ]);

    $category = Category::query()->create([
        'category_name' =>
        'Especial dashboard',

        'description' =>
        'Categoría para pruebas de analítica administrativa',
    ]);

    $pizza = Pizza::query()->create([
        'category_id' =>
        $category->id,

        'pizza_name' =>
        'Pizza especial dashboard',

        'description' =>
        'Pizza para pruebas del dashboard administrativo',

        'image_url' =>
        null,

        'is_visible' =>
        true,
    ]);

    /*
     * La tabla promotions exige estos campos:
     *
     * - promotion_name
     * - slug
     * - promotion_price
     * - starts_at
     * - ends_at
     *
     * promotion_type y selection_quantity tienen valores predeterminados,
     * pero también se envían para que la intención de la prueba sea explícita.
     */
    $promotion = Promotion::query()->create([
        'promotion_name' =>
        'Promoción dashboard',

        'slug' =>
        'promocion-dashboard-test',

        'description' =>
        'Promoción para pruebas de analítica administrativa',

        'banner_image_url' =>
        null,

        'promotion_type' =>
        Promotion::TYPE_FIXED_COMBO,

        'selection_quantity' =>
        2,

        'promotion_price' =>
        '15.00',

        'starts_at' =>
        '2026-01-01 00:00:00',

        'ends_at' =>
        '2026-12-31 23:59:59',

        'is_active' =>
        true,
    ]);

    return [
        'delivered_status_id' =>
        (int) $deliveredStatus->id,

        'cancelled_status_id' =>
        (int) $cancelledStatus->id,

        'delivery_type_id' =>
        (int) $deliveryType->id,

        'payment_method_id' =>
        (int) $paymentMethod->id,

        'size_id' =>
        (int) $size->id,

        'category_id' =>
        (int) $category->id,

        'pizza_id' =>
        (int) $pizza->id,

        'promotion_id' =>
        (int) $promotion->id,
    ];
}

/**
 * Crea un pedido con los campos requeridos por la tabla orders.
 *
 * @param array<string, int> $catalog
 */
function createSalesDashboardOrder(
    User $customer,
    array $catalog,
    string $number,
    string $orderedAt,
    int $statusId,
    string $total,
): Order {
    return Order::query()->create([
        'order_number' =>
        $number,

        'user_id' =>
        $customer->id,

        'ordered_at' =>
        $orderedAt,

        'subtotal' =>
        $total,

        'delivery_fee' =>
        '0.00',

        'total' =>
        $total,

        'delivery_type_id' =>
        $catalog['delivery_type_id'],

        'address' =>
        null,

        'delivery_lat' =>
        null,

        'delivery_lng' =>
        null,

        'delivery_maps_url' =>
        null,

        'delivery_place_id' =>
        null,

        'delivery_reference' =>
        null,

        'payment_method_id' =>
        $catalog['payment_method_id'],

        'order_status_id' =>
        $statusId,
    ]);
}

it(
    'requires authentication to access the sales dashboard',
    function (): void {
        /** @var TestCase $this */

        $this
            ->getJson(
                '/api/v1/admin/analytics/dashboard'
            )
            ->assertUnauthorized();
    },
);

it(
    'forbids customers from accessing the sales dashboard',
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
            ->getJson(
                '/api/v1/admin/analytics/dashboard'
            )
            ->assertForbidden();
    },
);

it(
    'validates the administrative analytics date range',
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
                '/api/v1/admin/analytics/dashboard'
                    . '?date_from=2026-08-10'
                    . '&date_to=2026-08-01'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
            ]);
    },
);

it(
    'calculates delivered sales pizzas promotions and cancellations',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $catalog =
            createSalesDashboardCatalog();

        /*
         * Pedido entregado 1:
         *
         * - dos pizzas individuales;
         * - total de $20.
         */
        $individualOrder =
            createSalesDashboardOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-TEST-001',
                orderedAt: '2026-08-02 20:00:00',
                statusId: $catalog['delivered_status_id'],
                total: '20.00',
            );

        OrderItem::query()->create([
            'order_id' =>
            $individualOrder->id,

            'promotion_id' =>
            null,

            'promotion_name' =>
            null,

            'pizza_id' =>
            $catalog['pizza_id'],

            'pizza_name' =>
            'Pizza especial dashboard',

            'pizza_id_second' =>
            null,

            'pizza_name_second' =>
            null,

            'size_id' =>
            $catalog['size_id'],

            'size_name' =>
            'Mediana dashboard',

            'category_name' =>
            'Especial dashboard',

            'category_name_second' =>
            null,

            'is_half_and_half' =>
            false,

            'quantity' =>
            2,

            'unit_price' =>
            '10.00',

            'subtotal' =>
            '20.00',
        ]);

        /*
         * Pedido entregado 2:
         *
         * - dos promociones;
         * - cada promoción contiene dos pizzas;
         * - total de cuatro pizzas físicas;
         * - total del pedido de $30.
         */
        $promotionOrder =
            createSalesDashboardOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-TEST-002',
                orderedAt: '2026-08-03 01:30:00',
                statusId: $catalog['delivered_status_id'],
                total: '30.00',
            );

        $promotionOrderItem =
            OrderItem::query()->create([
                'order_id' =>
                $promotionOrder->id,

                'promotion_id' =>
                $catalog['promotion_id'],

                'promotion_name' =>
                'Promoción dashboard',

                'pizza_id' =>
                null,

                'pizza_name' =>
                null,

                'pizza_id_second' =>
                null,

                'pizza_name_second' =>
                null,

                'size_id' =>
                $catalog['size_id'],

                'size_name' =>
                'Mediana dashboard',

                'category_name' =>
                null,

                'category_name_second' =>
                null,

                'is_half_and_half' =>
                false,

                'quantity' =>
                2,

                'unit_price' =>
                '15.00',

                'subtotal' =>
                '30.00',
            ]);

        /*
         * Primera pizza incluida en cada promoción.
         *
         * Como el OrderItem tiene quantity = 2, esta fila representa
         * dos unidades físicas de esta pizza.
         */
        OrderPromotionItem::query()->create([
            'order_item_id' =>
            $promotionOrderItem->id,

            'pizza_id' =>
            $catalog['pizza_id'],

            'pizza_name' =>
            'Pizza promocional uno',
        ]);

        /*
         * Segunda pizza incluida en cada promoción.
         *
         * También representa dos unidades físicas porque el paquete
         * promocional fue comprado dos veces.
         */
        OrderPromotionItem::query()->create([
            'order_item_id' =>
            $promotionOrderItem->id,

            'pizza_id' =>
            $catalog['pizza_id'],

            'pizza_name' =>
            'Pizza promocional dos',
        ]);

        /*
         * Pedido cancelado:
         *
         * - se contabiliza como cancelación;
         * - no suma ventas;
         * - no suma pizzas.
         */
        createSalesDashboardOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-TEST-003',
            orderedAt: '2026-08-03 03:00:00',
            statusId: $catalog['cancelled_status_id'],
            total: '99.00',
        );

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/dashboard'
                    . '?date_from=2026-08-02'
                    . '&date_to=2026-08-03'
                    . '&timezone=UTC'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.summary.gross_sales',
                50,
            )
            ->assertJsonPath(
                'data.summary.refunds',
                0,
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
                'data.summary.pizzas_sold',
                6,
            )
            ->assertJsonPath(
                'data.summary.promotions_sold',
                2,
            )
            ->assertJsonPath(
                'data.summary.average_ticket',
                25,
            )
            ->assertJsonPath(
                'data.summary.cancellation_rate',
                33.33,
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

        $catalog =
            createSalesDashboardCatalog();

        /*
         * Pedido fuera del rango.
         */
        createSalesDashboardOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-OUTSIDE-001',
            orderedAt: '2026-07-31 23:59:59',
            statusId: $catalog['delivered_status_id'],
            total: '100.00',
        );

        /*
         * Pedido dentro del rango.
         */
        createSalesDashboardOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-INSIDE-001',
            orderedAt: '2026-08-01 12:00:00',
            statusId: $catalog['delivered_status_id'],
            total: '20.00',
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/dashboard'
                    . '?date_from=2026-08-01'
                    . '&date_to=2026-08-01'
                    . '&timezone=UTC'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.gross_sales',
                20,
            )
            ->assertJsonPath(
                'data.summary.net_sales',
                20,
            )
            ->assertJsonPath(
                'data.summary.delivered_orders',
                1,
            )
            ->assertJsonPath(
                'data.summary.cancelled_orders',
                0,
            );
    },


);
it(
    'returns daily sales including dates without activity',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $catalog =
            createSalesDashboardCatalog();

        $firstOrder =
            createSalesDashboardOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-DAILY-001',
                orderedAt: '2026-08-01 20:00:00',
                statusId: $catalog['delivered_status_id'],
                total: '20.00',
            );

        OrderItem::query()->create([
            'order_id' =>
            $firstOrder->id,

            'promotion_id' =>
            null,

            'promotion_name' =>
            null,

            'pizza_id' =>
            $catalog['pizza_id'],

            'pizza_name' =>
            'Pizza especial dashboard',

            'pizza_id_second' =>
            null,

            'pizza_name_second' =>
            null,

            'size_id' =>
            $catalog['size_id'],

            'size_name' =>
            'Mediana dashboard',

            'category_name' =>
            'Especial dashboard',

            'category_name_second' =>
            null,

            'is_half_and_half' =>
            false,

            'quantity' =>
            2,

            'unit_price' =>
            '10.00',

            'subtotal' =>
            '20.00',
        ]);

        createSalesDashboardOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-DAILY-CANCELLED',
            orderedAt: '2026-08-03 18:00:00',
            statusId: $catalog['cancelled_status_id'],
            total: '40.00',
        );

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/daily'
                    . '?date_from=2026-08-01'
                    . '&date_to=2026-08-03'
                    . '&timezone=America/Guayaquil'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
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
                20,
            )
            ->assertJsonPath(
                'data.days.0.delivered_orders',
                1,
            )
            ->assertJsonPath(
                'data.days.0.pizzas_sold',
                2,
            )
            ->assertJsonPath(
                'data.days.1.date',
                '2026-08-02',
            )
            ->assertJsonPath(
                'data.days.1.gross_sales',
                0,
            )
            ->assertJsonPath(
                'data.days.1.delivered_orders',
                0,
            )
            ->assertJsonPath(
                'data.days.2.date',
                '2026-08-03',
            )
            ->assertJsonPath(
                'data.days.2.cancelled_orders',
                1,
            )
            ->assertJsonPath(
                'data.totals.gross_sales',
                20,
            )
            ->assertJsonPath(
                'data.totals.delivered_orders',
                1,
            )
            ->assertJsonPath(
                'data.totals.cancelled_orders',
                1,
            )
            ->assertJsonPath(
                'data.totals.cancellation_rate',
                50,
            );
    },
);

it(
    'counts promotion pizzas correctly in daily sales',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $catalog =
            createSalesDashboardCatalog();

        $order =
            createSalesDashboardOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-DAILY-PROMO',
                orderedAt: '2026-08-02 19:00:00',
                statusId: $catalog['delivered_status_id'],
                total: '30.00',
            );

        $orderItem =
            OrderItem::query()->create([
                'order_id' =>
                $order->id,

                'promotion_id' =>
                $catalog['promotion_id'],

                'promotion_name' =>
                'Promoción dashboard',

                'pizza_id' =>
                null,

                'pizza_name' =>
                null,

                'pizza_id_second' =>
                null,

                'pizza_name_second' =>
                null,

                'size_id' =>
                $catalog['size_id'],

                'size_name' =>
                'Mediana dashboard',

                'category_name' =>
                null,

                'category_name_second' =>
                null,

                'is_half_and_half' =>
                false,

                'quantity' =>
                2,

                'unit_price' =>
                '15.00',

                'subtotal' =>
                '30.00',
            ]);

        OrderPromotionItem::query()->create([
            'order_item_id' =>
            $orderItem->id,

            'pizza_id' =>
            $catalog['pizza_id'],

            'pizza_name' =>
            'Pizza promocional uno',
        ]);

        OrderPromotionItem::query()->create([
            'order_item_id' =>
            $orderItem->id,

            'pizza_id' =>
            $catalog['pizza_id'],

            'pizza_name' =>
            'Pizza promocional dos',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/daily'
                    . '?date_from=2026-08-02'
                    . '&date_to=2026-08-02'
                    . '&timezone=America/Guayaquil'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.days.0.gross_sales',
                30,
            )
            ->assertJsonPath(
                'data.days.0.delivered_orders',
                1,
            )
            ->assertJsonPath(
                'data.days.0.promotions_sold',
                2,
            )
            ->assertJsonPath(
                'data.days.0.pizzas_sold',
                4,
            )
            ->assertJsonPath(
                'data.days.0.average_ticket',
                30,
            );
    },
);

it(
    'uses the local business day without shifting it to UTC',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $catalog =
            createSalesDashboardCatalog();

        createSalesDashboardOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-LOCAL-TIME-001',
            orderedAt: '2026-08-01 00:30:00',
            statusId: $catalog['delivered_status_id'],
            total: '18.00',
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/daily'
                    . '?date_from=2026-08-01'
                    . '&date_to=2026-08-01'
                    . '&timezone=America/Guayaquil'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.days.0.date',
                '2026-08-01',
            )
            ->assertJsonPath(
                'data.days.0.gross_sales',
                18,
            )
            ->assertJsonPath(
                'data.days.0.delivered_orders',
                1,
            );
    },
);


it(
    'returns the complete hourly sales timeline and peak hours',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $catalog =
            createSalesDashboardCatalog();

        /*
         * Pedido de las 18:00:
         * - $20;
         * - dos pizzas.
         */
        $firstOrder =
            createSalesDashboardOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-HOURLY-001',
                orderedAt: '2026-08-01 18:10:00',
                statusId: $catalog['delivered_status_id'],
                total: '20.00',
            );

        OrderItem::query()->create([
            'order_id' =>
            $firstOrder->id,

            'promotion_id' =>
            null,

            'promotion_name' =>
            null,

            'pizza_id' =>
            $catalog['pizza_id'],

            'pizza_name' =>
            'Pizza especial dashboard',

            'pizza_id_second' =>
            null,

            'pizza_name_second' =>
            null,

            'size_id' =>
            $catalog['size_id'],

            'size_name' =>
            'Mediana dashboard',

            'category_name' =>
            'Especial dashboard',

            'category_name_second' =>
            null,

            'is_half_and_half' =>
            false,

            'quantity' =>
            2,

            'unit_price' =>
            '10.00',

            'subtotal' =>
            '20.00',
        ]);

        /*
         * Dos pedidos entregados durante las 20:00.
         *
         * Resultado de la franja:
         * - $45;
         * - dos pedidos;
         * - dos pizzas.
         */
        $secondOrder =
            createSalesDashboardOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-HOURLY-002',
                orderedAt: '2026-08-01 20:05:00',
                statusId: $catalog['delivered_status_id'],
                total: '25.00',
            );

        OrderItem::query()->create([
            'order_id' =>
            $secondOrder->id,

            'promotion_id' =>
            null,

            'promotion_name' =>
            null,

            'pizza_id' =>
            $catalog['pizza_id'],

            'pizza_name' =>
            'Pizza especial dashboard',

            'pizza_id_second' =>
            null,

            'pizza_name_second' =>
            null,

            'size_id' =>
            $catalog['size_id'],

            'size_name' =>
            'Mediana dashboard',

            'category_name' =>
            'Especial dashboard',

            'category_name_second' =>
            null,

            'is_half_and_half' =>
            false,

            'quantity' =>
            1,

            'unit_price' =>
            '25.00',

            'subtotal' =>
            '25.00',
        ]);

        $thirdOrder =
            createSalesDashboardOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-HOURLY-003',
                orderedAt: '2026-08-01 20:45:00',
                statusId: $catalog['delivered_status_id'],
                total: '20.00',
            );

        OrderItem::query()->create([
            'order_id' =>
            $thirdOrder->id,

            'promotion_id' =>
            null,

            'promotion_name' =>
            null,

            'pizza_id' =>
            $catalog['pizza_id'],

            'pizza_name' =>
            'Pizza especial dashboard',

            'pizza_id_second' =>
            null,

            'pizza_name_second' =>
            null,

            'size_id' =>
            $catalog['size_id'],

            'size_name' =>
            'Mediana dashboard',

            'category_name' =>
            'Especial dashboard',

            'category_name_second' =>
            null,

            'is_half_and_half' =>
            false,

            'quantity' =>
            1,

            'unit_price' =>
            '20.00',

            'subtotal' =>
            '20.00',
        ]);

        /*
         * Pedido cancelado durante las 20:00.
         */
        createSalesDashboardOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-HOURLY-CANCELLED',
            orderedAt: '2026-08-01 20:50:00',
            statusId: $catalog['cancelled_status_id'],
            total: '50.00',
        );

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/hourly'
                    . '?date_from=2026-08-01'
                    . '&date_to=2026-08-01'
                    . '&timezone=America/Guayaquil'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
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
                'data.hours.18.hour',
                18,
            )
            ->assertJsonPath(
                'data.hours.18.gross_sales',
                20,
            )
            ->assertJsonPath(
                'data.hours.18.delivered_orders',
                1,
            )
            ->assertJsonPath(
                'data.hours.18.pizzas_sold',
                2,
            )
            ->assertJsonPath(
                'data.hours.20.hour',
                20,
            )
            ->assertJsonPath(
                'data.hours.20.label',
                '20:00',
            )
            ->assertJsonPath(
                'data.hours.20.gross_sales',
                45,
            )
            ->assertJsonPath(
                'data.hours.20.delivered_orders',
                2,
            )
            ->assertJsonPath(
                'data.hours.20.cancelled_orders',
                1,
            )
            ->assertJsonPath(
                'data.hours.20.pizzas_sold',
                2,
            )
            ->assertJsonPath(
                'data.hours.20.average_ticket',
                22.5,
            )
            ->assertJsonPath(
                'data.hours.20.cancellation_rate',
                33.33,
            )
            ->assertJsonPath(
                'data.summary.gross_sales',
                65,
            )
            ->assertJsonPath(
                'data.summary.delivered_orders',
                3,
            )
            ->assertJsonPath(
                'data.summary.cancelled_orders',
                1,
            )
            ->assertJsonPath(
                'data.summary.pizzas_sold',
                4,
            )
            ->assertJsonPath(
                'data.summary.peak_sales_hour',
                20,
            )
            ->assertJsonPath(
                'data.summary.peak_sales_hour_label',
                '20:00',
            )
            ->assertJsonPath(
                'data.summary.peak_sales_amount',
                45,
            )
            ->assertJsonPath(
                'data.summary.peak_orders_hour',
                20,
            )
            ->assertJsonPath(
                'data.summary.peak_orders_count',
                2,
            );
    },
);

it(
    'counts promotion units and promotion pizzas by hour',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $catalog =
            createSalesDashboardCatalog();

        $order =
            createSalesDashboardOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-HOURLY-PROMOTION',
                orderedAt: '2026-08-02 19:30:00',
                statusId: $catalog['delivered_status_id'],
                total: '30.00',
            );

        $orderItem =
            OrderItem::query()->create([
                'order_id' =>
                $order->id,

                'promotion_id' =>
                $catalog['promotion_id'],

                'promotion_name' =>
                'Promoción dashboard',

                'pizza_id' =>
                null,

                'pizza_name' =>
                null,

                'pizza_id_second' =>
                null,

                'pizza_name_second' =>
                null,

                'size_id' =>
                $catalog['size_id'],

                'size_name' =>
                'Mediana dashboard',

                'category_name' =>
                null,

                'category_name_second' =>
                null,

                'is_half_and_half' =>
                false,

                'quantity' =>
                2,

                'unit_price' =>
                '15.00',

                'subtotal' =>
                '30.00',
            ]);

        OrderPromotionItem::query()->create([
            'order_item_id' =>
            $orderItem->id,

            'pizza_id' =>
            $catalog['pizza_id'],

            'pizza_name' =>
            'Pizza promocional uno',
        ]);

        OrderPromotionItem::query()->create([
            'order_item_id' =>
            $orderItem->id,

            'pizza_id' =>
            $catalog['pizza_id'],

            'pizza_name' =>
            'Pizza promocional dos',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/sales/hourly'
                    . '?date_from=2026-08-02'
                    . '&date_to=2026-08-02'
                    . '&timezone=America/Guayaquil'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.hours.19.hour',
                19,
            )
            ->assertJsonPath(
                'data.hours.19.gross_sales',
                30,
            )
            ->assertJsonPath(
                'data.hours.19.promotions_sold',
                2,
            )
            ->assertJsonPath(
                'data.hours.19.pizzas_sold',
                4,
            )
            ->assertJsonPath(
                'data.summary.promotions_sold',
                2,
            )
            ->assertJsonPath(
                'data.summary.pizzas_sold',
                4,
            );
    },
);

it(
    'returns empty peak hour values when the period has no delivered sales',
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
                    . '?date_from=2026-08-01'
                    . '&date_to=2026-08-01'
                    . '&timezone=America/Guayaquil'
            )
            ->assertOk()
            ->assertJsonCount(
                24,
                'data.hours',
            )
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
    'calculates product performance including half and half pizzas and promotions',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $catalog =
            createSalesDashboardCatalog();

        $secondPizza = Pizza::query()->create([
            'category_id' =>
            $catalog['category_id'],

            'pizza_name' =>
            'Segunda pizza dashboard',

            'description' =>
            'Segundo sabor para pruebas de rendimiento',

            'image_url' =>
            null,

            'is_visible' =>
            true,
        ]);

        /*
         * Pedido 1:
         * dos pizzas completas del primer sabor.
         */
        $completeOrder =
            createSalesDashboardOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-PRODUCT-001',
                orderedAt: '2026-08-01 18:00:00',
                statusId: $catalog['delivered_status_id'],
                total: '20.00',
            );

        OrderItem::query()->create([
            'order_id' =>
            $completeOrder->id,

            'promotion_id' =>
            null,

            'promotion_name' =>
            null,

            'pizza_id' =>
            $catalog['pizza_id'],

            'pizza_name' =>
            'Pizza especial dashboard',

            'pizza_id_second' =>
            null,

            'pizza_name_second' =>
            null,

            'size_id' =>
            $catalog['size_id'],

            'size_name' =>
            'Mediana dashboard',

            'category_name' =>
            'Especial dashboard',

            'category_name_second' =>
            null,

            'is_half_and_half' =>
            false,

            'quantity' =>
            2,

            'unit_price' =>
            '10.00',

            'subtotal' =>
            '20.00',
        ]);

        /*
         * Pedido 2:
         * dos pizzas físicas mitad y mitad.
         *
         * Cada sabor recibe:
         * 2 × 0.5 = 1 unidad equivalente.
         */
        $halfOrder =
            createSalesDashboardOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-PRODUCT-002',
                orderedAt: '2026-08-01 19:00:00',
                statusId: $catalog['delivered_status_id'],
                total: '24.00',
            );

        OrderItem::query()->create([
            'order_id' =>
            $halfOrder->id,

            'promotion_id' =>
            null,

            'promotion_name' =>
            null,

            'pizza_id' =>
            $catalog['pizza_id'],

            'pizza_name' =>
            'Pizza especial dashboard',

            'pizza_id_second' =>
            $secondPizza->id,

            'pizza_name_second' =>
            'Segunda pizza dashboard',

            'size_id' =>
            $catalog['size_id'],

            'size_name' =>
            'Mediana dashboard',

            'category_name' =>
            'Especial dashboard',

            'category_name_second' =>
            'Especial dashboard',

            'is_half_and_half' =>
            true,

            'quantity' =>
            2,

            'unit_price' =>
            '12.00',

            'subtotal' =>
            '24.00',
        ]);

        /*
         * Pedido 3:
         * dos paquetes promocionales con dos pizzas cada uno.
         *
         * Resultado:
         * - dos paquetes;
         * - cuatro pizzas físicas;
         * - dos unidades del primer sabor;
         * - dos unidades del segundo sabor.
         */
        $promotionOrder =
            createSalesDashboardOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-PRODUCT-003',
                orderedAt: '2026-08-01 20:00:00',
                statusId: $catalog['delivered_status_id'],
                total: '30.00',
            );

        $promotionItem =
            OrderItem::query()->create([
                'order_id' =>
                $promotionOrder->id,

                'promotion_id' =>
                $catalog['promotion_id'],

                'promotion_name' =>
                'Promoción dashboard',

                'pizza_id' =>
                null,

                'pizza_name' =>
                null,

                'pizza_id_second' =>
                null,

                'pizza_name_second' =>
                null,

                'size_id' =>
                $catalog['size_id'],

                'size_name' =>
                'Mediana dashboard',

                'category_name' =>
                null,

                'category_name_second' =>
                null,

                'is_half_and_half' =>
                false,

                'quantity' =>
                2,

                'unit_price' =>
                '15.00',

                'subtotal' =>
                '30.00',
            ]);

        OrderPromotionItem::query()->create([
            'order_item_id' =>
            $promotionItem->id,

            'pizza_id' =>
            $catalog['pizza_id'],

            'pizza_name' =>
            'Pizza especial dashboard',
        ]);

        OrderPromotionItem::query()->create([
            'order_item_id' =>
            $promotionItem->id,

            'pizza_id' =>
            $secondPizza->id,

            'pizza_name' =>
            'Segunda pizza dashboard',
        ]);

        /*
         * Pedido cancelado:
         * no debe aparecer en el rendimiento.
         */
        createSalesDashboardOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-PRODUCT-CANCELLED',
            orderedAt: '2026-08-01 21:00:00',
            statusId: $catalog['cancelled_status_id'],
            total: '100.00',
        );

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/products'
                    . '?date_from=2026-08-01'
                    . '&date_to=2026-08-01'
                    . '&timezone=America/Guayaquil'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonCount(
                2,
                'data.pizzas',
            )
            ->assertJsonPath(
                'data.pizzas.0.pizza_id',
                $catalog['pizza_id'],
            )
            ->assertJsonPath(
                'data.pizzas.0.pizza_name',
                'Pizza especial dashboard',
            )
            ->assertJsonPath(
                'data.pizzas.0.equivalent_units',
                5,
            )
            ->assertJsonPath(
                'data.pizzas.0.complete_units',
                2,
            )
            ->assertJsonPath(
                'data.pizzas.0.half_units',
                1,
            )
            ->assertJsonPath(
                'data.pizzas.0.promotion_units',
                2,
            )
            ->assertJsonPath(
                'data.pizzas.1.pizza_id',
                $secondPizza->id,
            )
            ->assertJsonPath(
                'data.pizzas.1.equivalent_units',
                3,
            )
            ->assertJsonPath(
                'data.pizzas.1.complete_units',
                0,
            )
            ->assertJsonPath(
                'data.pizzas.1.half_units',
                1,
            )
            ->assertJsonPath(
                'data.pizzas.1.promotion_units',
                2,
            )
            ->assertJsonPath(
                'data.promotions.0.promotion_id',
                $catalog['promotion_id'],
            )
            ->assertJsonPath(
                'data.promotions.0.packages_sold',
                2,
            )
            ->assertJsonPath(
                'data.promotions.0.gross_sales',
                30,
            )
            ->assertJsonPath(
                'data.sizes.0.size_id',
                $catalog['size_id'],
            )
            ->assertJsonPath(
                'data.sizes.0.pizza_units',
                8,
            )
            ->assertJsonPath(
                'data.summary.total_pizza_units',
                8,
            )
            ->assertJsonPath(
                'data.summary.unique_pizzas_sold',
                2,
            )
            ->assertJsonPath(
                'data.summary.total_promotion_packages',
                2,
            )
            ->assertJsonPath(
                'data.summary.promotion_gross_sales',
                30,
            )
            ->assertJsonPath(
                'data.summary.top_pizza.pizza_id',
                $catalog['pizza_id'],
            )
            ->assertJsonPath(
                'data.summary.top_size.size_id',
                $catalog['size_id'],
            );
    },
);
it(
    'returns empty product performance when the period has no delivered orders',
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
                '/api/v1/admin/analytics/products'
                    . '?date_from=2026-08-01'
                    . '&date_to=2026-08-01'
                    . '&timezone=America/Guayaquil'
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.pizzas',
            )
            ->assertJsonCount(
                0,
                'data.promotions',
            )
            ->assertJsonCount(
                0,
                'data.sizes',
            )
            ->assertJsonPath(
                'data.summary.total_pizza_units',
                0,
            )
            ->assertJsonPath(
                'data.summary.unique_pizzas_sold',
                0,
            )
            ->assertJsonPath(
                'data.summary.total_promotion_packages',
                0,
            )
            ->assertJsonPath(
                'data.summary.promotion_gross_sales',
                0,
            )
            ->assertJsonPath(
                'data.summary.top_pizza',
                null,
            )
            ->assertJsonPath(
                'data.summary.top_promotion',
                null,
            )
            ->assertJsonPath(
                'data.summary.top_size',
                null,
            );
    },
);
it(
    'calculates collected and pending payments by recognition date',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $catalog =
            createSalesDashboardCatalog();

        $transferMethod = PaymentMethod::query()
            ->firstOrCreate(
                [
                    'name' => 'transfer',
                ],
                [
                    'description' =>
                    'Transferencia bancaria',

                    'active' =>
                    true,
                ],
            );

        $cardMethod = PaymentMethod::query()
            ->firstOrCreate(
                [
                    'name' => 'card',
                ],
                [
                    'description' =>
                    'Pago mediante PayPal',

                    'active' =>
                    true,
                ],
            );

        /*
         * Efectivo cobrado al entregar.
         */
        $cashOrder =
            createSalesDashboardOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-CASH-001',
                orderedAt: '2026-07-31 18:00:00',
                statusId: $catalog['delivered_status_id'],
                total: '20.00',
            );

        OrderStatusChange::query()->create([
            'order_id' =>
            $cashOrder->id,

            'from_order_status_id' =>
            null,

            'to_order_status_id' =>
            $catalog['delivered_status_id'],

            'changed_by_user_id' =>
            $admin->id,

            'changed_at' =>
            '2026-08-01 18:30:00',

            'note' =>
            'Pedido entregado y cobrado en efectivo',
        ]);

        /*
         * Transferencia aprobada.
         */
        $transferCatalog = [
            ...$catalog,

            'payment_method_id' =>
            (int) $transferMethod->id,
        ];

        $approvedTransferOrder =
            createSalesDashboardOrder(
                customer: $customer,
                catalog: $transferCatalog,
                number: 'CH-TRANSFER-001',
                orderedAt: '2026-08-01 17:00:00',
                statusId: $catalog['delivered_status_id'],
                total: '30.00',
            );

        PaymentReceipt::query()->create([
            'uuid' =>
            (string) Str::uuid(),

            'order_id' =>
            $approvedTransferOrder->id,

            'user_id' =>
            $customer->id,

            'disk' =>
            'payment_receipts',

            'file_path' =>
            'tests/approved-transfer.jpg',

            'original_name' =>
            'transferencia.jpg',

            'mime_type' =>
            'image/jpeg',

            'file_size' =>
            1024,

            'status' =>
            PaymentReceiptStatus::Approved,

            'rejection_reason' =>
            null,

            'submitted_at' =>
            '2026-08-01 17:05:00',

            'reviewed_at' =>
            '2026-08-01 17:20:00',

            'reviewed_by' =>
            $admin->id,

            'expires_at' =>
            null,

            'file_deleted_at' =>
            null,
        ]);

        /*
         * Transferencia pendiente.
         */
        $pendingTransferOrder =
            createSalesDashboardOrder(
                customer: $customer,
                catalog: $transferCatalog,
                number: 'CH-TRANSFER-002',
                orderedAt: '2026-08-01 19:00:00',
                statusId: $catalog['delivered_status_id'],
                total: '25.00',
            );

        PaymentReceipt::query()->create([
            'uuid' =>
            (string) Str::uuid(),

            'order_id' =>
            $pendingTransferOrder->id,

            'user_id' =>
            $customer->id,

            'disk' =>
            'payment_receipts',

            'file_path' =>
            'tests/pending-transfer.jpg',

            'original_name' =>
            'pendiente.jpg',

            'mime_type' =>
            'image/jpeg',

            'file_size' =>
            1024,

            'status' =>
            PaymentReceiptStatus::Pending,

            'rejection_reason' =>
            null,

            'submitted_at' =>
            '2026-08-01 19:10:00',

            'reviewed_at' =>
            null,

            'reviewed_by' =>
            null,

            'expires_at' =>
            null,

            'file_deleted_at' =>
            null,
        ]);

        /*
         * PayPal completado.
         */
        $paypalCatalog = [
            ...$catalog,

            'payment_method_id' =>
            (int) $cardMethod->id,
        ];

        $paypalOrder =
            createSalesDashboardOrder(
                customer: $customer,
                catalog: $paypalCatalog,
                number: 'CH-PAYPAL-001',
                orderedAt: '2026-08-01 20:00:00',
                statusId: $catalog['delivered_status_id'],
                total: '40.00',
            );

        Payment::query()->create([
            'uuid' =>
            (string) Str::uuid(),

            'idempotency_key' =>
            (string) Str::uuid(),

            'user_id' =>
            $customer->id,

            'cart_id' =>
            null,

            'order_id' =>
            $paypalOrder->id,

            'provider' =>
            'paypal',

            'provider_order_id' =>
            'PAYPAL-ORDER-001',

            'provider_capture_id' =>
            'PAYPAL-CAPTURE-001',

            'provider_status' =>
            'COMPLETED',

            'amount' =>
            '40.00',

            'currency' =>
            'USD',

            'status' =>
            PaymentStatus::COMPLETED,

            'paid_at' =>
            '2026-08-01 20:10:00',
        ]);

        /*
 * PayPal pendiente.
 *
 * Se utiliza forceCreate porque created_at y updated_at no forman
 * parte de $fillable en Payment. Necesitamos fijar la fecha histórica
 * para comprobar correctamente el filtro del reporte.
 */
        Payment::query()->forceCreate([
            'uuid' =>
            (string) Str::uuid(),

            'idempotency_key' =>
            (string) Str::uuid(),

            'user_id' =>
            $customer->id,

            'cart_id' =>
            null,

            'order_id' =>
            null,

            'provider' =>
            'paypal',

            'provider_order_id' =>
            'PAYPAL-ORDER-002',

            'provider_capture_id' =>
            null,

            'provider_status' =>
            'CREATED',

            'amount' =>
            '15.00',

            'currency' =>
            'USD',

            'status' =>
            PaymentStatus::PENDING,

            'created_at' =>
            '2026-08-01 21:00:00',

            'updated_at' =>
            '2026-08-01 21:00:00',
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/payments'
                    . '?date_from=2026-08-01'
                    . '&date_to=2026-08-01'
                    . '&timezone=America/Guayaquil'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.summary.cash_amount',
                20,
            )
            ->assertJsonPath(
                'data.summary.transfer_amount',
                30,
            )
            ->assertJsonPath(
                'data.summary.paypal_amount',
                40,
            )
            ->assertJsonPath(
                'data.summary.collected_total',
                90,
            )
            ->assertJsonPath(
                'data.summary.cash_orders',
                1,
            )
            ->assertJsonPath(
                'data.summary.transfer_orders',
                1,
            )
            ->assertJsonPath(
                'data.summary.paypal_payments',
                1,
            )
            ->assertJsonPath(
                'data.pending.transfer.amount',
                25,
            )
            ->assertJsonPath(
                'data.pending.transfer.transactions',
                1,
            )
            ->assertJsonPath(
                'data.pending.paypal.amount',
                15,
            )
            ->assertJsonPath(
                'data.pending.paypal.transactions',
                1,
            )
            ->assertJsonPath(
                'data.summary.pending_amount',
                40,
            )
            ->assertJsonPath(
                'data.summary.pending_transactions',
                2,
            );
    },
);
it(
    'returns an empty financial summary when the period has no movements',
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
                '/api/v1/admin/analytics/payments'
                    . '?date_from=2026-08-01'
                    . '&date_to=2026-08-01'
                    . '&timezone=America/Guayaquil'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.collected_total',
                0,
            )
            ->assertJsonPath(
                'data.summary.cash_amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.transfer_amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.paypal_amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.pending_amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.pending_transactions',
                0,
            )
            ->assertJsonPath(
                'data.refunds.refunded_payments',
                0,
            )
            ->assertJsonPath(
                'data.refunds.partially_refunded_payments',
                0,
            )
            ->assertJsonPath(
                'data.refunds.refundable_amount_available',
                false,
            );
    },
);
