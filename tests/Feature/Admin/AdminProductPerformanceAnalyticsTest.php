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
 *     delivered_status_id: int,
 *     pending_status_id: int,
 *     delivery_type_id: int,
 *     payment_method_id: int,
 *     category_id: int,
 *     small_id: int,
 *     medium_id: int,
 *     large_id: int,
 *     pizza_a_id: int,
 *     pizza_b_id: int,
 *     pizza_c_id: int,
 *     promotion_id: int
 * }
 */
function createProductPerformanceCatalog(): array
{
    $deliveredStatus = OrderStatus::query()
        ->firstOrCreate([
            'status_name' => 'delivered',
        ]);

    $pendingStatus = OrderStatus::query()
        ->firstOrCreate([
            'status_name' => 'pending',
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
                'description' => 'Pago en efectivo para analítica',
                'active' => true,
            ],
        );

    $category = Category::query()->create([
        'category_name' => 'Especiales analítica',
        'description' => 'Categoría para rendimiento de productos.',
    ]);

    $small = Size::query()->create([
        'size_name' => 'Pequeña analítica',
        'portion' => 4,
    ]);

    $medium = Size::query()->create([
        'size_name' => 'Mediana analítica',
        'portion' => 8,
    ]);

    $large = Size::query()->create([
        'size_name' => 'Familiar analítica',
        'portion' => 12,
    ]);

    $pizzaA = Pizza::query()->create([
        'category_id' => $category->id,
        'pizza_name' => 'Americana analítica',
        'description' => null,
        'image_url' => null,
        'is_visible' => true,
    ]);

    $pizzaB = Pizza::query()->create([
        'category_id' => $category->id,
        'pizza_name' => 'Hawaiana analítica',
        'description' => null,
        'image_url' => null,
        'is_visible' => true,
    ]);

    $pizzaC = Pizza::query()->create([
        'category_id' => $category->id,
        'pizza_name' => 'Pepperoni analítica',
        'description' => null,
        'image_url' => null,
        'is_visible' => true,
    ]);

    $promotion = Promotion::query()->create([
        'promotion_name' => 'Combo analítica',
        'slug' => 'combo-analitica-productos',
        'description' => null,
        'banner_image_url' => null,
        'promotion_type' => Promotion::TYPE_FIXED_COMBO,
        'selection_quantity' => 2,
        'promotion_price' => '15.00',
        'starts_at' => '2026-01-01 00:00:00',
        'ends_at' => '2026-12-31 23:59:59',
        'is_active' => true,
    ]);

    return [
        'delivered_status_id' => (int) $deliveredStatus->id,
        'pending_status_id' => (int) $pendingStatus->id,
        'delivery_type_id' => (int) $deliveryType->id,
        'payment_method_id' => (int) $paymentMethod->id,
        'category_id' => (int) $category->id,
        'small_id' => (int) $small->id,
        'medium_id' => (int) $medium->id,
        'large_id' => (int) $large->id,
        'pizza_a_id' => (int) $pizzaA->id,
        'pizza_b_id' => (int) $pizzaB->id,
        'pizza_c_id' => (int) $pizzaC->id,
        'promotion_id' => (int) $promotion->id,
    ];
}

/**
 * @param  array<string, int>  $catalog
 */
function createProductPerformanceOrder(
    User $customer,
    array $catalog,
    string $number,
    string $orderedAt,
    int $statusId,
    string $total,
): Order {
    return Order::query()->create([
        'order_number' => $number,
        'user_id' => $customer->id,
        'ordered_at' => $orderedAt,
        'subtotal' => $total,
        'delivery_fee' => '0.00',
        'total' => $total,
        'delivery_type_id' => $catalog['delivery_type_id'],
        'address' => null,
        'delivery_lat' => null,
        'delivery_lng' => null,
        'delivery_maps_url' => null,
        'delivery_place_id' => null,
        'delivery_reference' => null,
        'payment_method_id' => $catalog['payment_method_id'],
        'order_status_id' => $statusId,
    ]);
}

it(
    'requires authentication to access product performance analytics',
    function (): void {
        /** @var TestCase $this */
        $this
            ->getJson('/api/v1/admin/analytics/products')
            ->assertUnauthorized();
    },
);

it(
    'forbids non administrators from accessing product performance analytics',
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
            ->getJson('/api/v1/admin/analytics/products')
            ->assertForbidden();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->getJson('/api/v1/admin/analytics/products')
            ->assertForbidden();
    },
);

it(
    'validates the product analytics date range and timezone',
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
                    .'?date_from=2026-08-10'
                    .'&date_to=2026-08-01'
                    .'&timezone=Europe/Madrid',
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
                '/api/v1/admin/analytics/products'
                    .'?date_from=2025-01-01'
                    .'&date_to=2026-08-01',
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

it(
    'returns an empty product performance report when there are no delivered sales',
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
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-03'
                    .'&timezone=America/Guayaquil',
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'message',
                'Rendimiento de productos recuperado correctamente.',
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
            )
            ->assertJsonPath(
                'data.pizzas',
                [],
            )
            ->assertJsonPath(
                'data.promotions',
                [],
            )
            ->assertJsonPath(
                'data.sizes',
                [],
            );
    },
);

it(
    'calculates complete half and promotional pizza performance',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $catalog = createProductPerformanceCatalog();

        /*
         * Dos pizzas completas Americana, tamaño mediano.
         */
        $completeOrder = createProductPerformanceOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-PRODUCT-001',
            orderedAt: '2026-08-01 12:00:00',
            statusId: $catalog['delivered_status_id'],
            total: '20.00',
        );

        OrderItem::query()->create([
            'order_id' => $completeOrder->id,
            'promotion_id' => null,
            'promotion_name' => null,
            'pizza_id' => $catalog['pizza_a_id'],
            'pizza_name' => 'Americana histórica',
            'pizza_id_second' => null,
            'pizza_name_second' => null,
            'size_id' => $catalog['medium_id'],
            'size_name' => 'Mediana histórica',
            'category_name' => 'Especiales históricas',
            'category_name_second' => null,
            'is_half_and_half' => false,
            'quantity' => 2,
            'unit_price' => '10.00',
            'subtotal' => '20.00',
        ]);

        /*
         * Dos pizzas físicas mitad Americana y mitad Hawaiana.
         *
         * Cada sabor recibe:
         * 2 × 0.5 = 1 unidad equivalente.
         */
        $halfOrder = createProductPerformanceOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-PRODUCT-002',
            orderedAt: '2026-08-02 13:00:00',
            statusId: $catalog['delivered_status_id'],
            total: '24.00',
        );

        OrderItem::query()->create([
            'order_id' => $halfOrder->id,
            'promotion_id' => null,
            'promotion_name' => null,
            'pizza_id' => $catalog['pizza_a_id'],
            'pizza_name' => 'Americana histórica',
            'pizza_id_second' => $catalog['pizza_b_id'],
            'pizza_name_second' => 'Hawaiana histórica',
            'size_id' => $catalog['large_id'],
            'size_name' => 'Familiar histórica',
            'category_name' => 'Especiales históricas',
            'category_name_second' => 'Especiales históricas',
            'is_half_and_half' => true,
            'quantity' => 2,
            'unit_price' => '12.00',
            'subtotal' => '24.00',
        ]);

        /*
         * Dos paquetes promocionales.
         *
         * Cada paquete contiene:
         * - una Americana;
         * - una Pepperoni.
         *
         * Resultado:
         * - 2 paquetes vendidos;
         * - 4 pizzas físicas;
         * - $30 de ingreso histórico.
         */
        $promotionOrder = createProductPerformanceOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-PRODUCT-003',
            orderedAt: '2026-08-03 14:00:00',
            statusId: $catalog['delivered_status_id'],
            total: '30.00',
        );

        $promotionOrderItem = OrderItem::query()->create([
            'order_id' => $promotionOrder->id,
            'promotion_id' => $catalog['promotion_id'],
            'promotion_name' => 'Combo histórico',
            'pizza_id' => null,
            'pizza_name' => null,
            'pizza_id_second' => null,
            'pizza_name_second' => null,
            'size_id' => $catalog['small_id'],
            'size_name' => 'Pequeña histórica',
            'category_name' => null,
            'category_name_second' => null,
            'is_half_and_half' => false,
            'quantity' => 2,
            'unit_price' => '15.00',
            'subtotal' => '30.00',
        ]);

        OrderPromotionItem::query()->create([
            'order_item_id' => $promotionOrderItem->id,
            'pizza_id' => $catalog['pizza_a_id'],
            'pizza_name' => 'Americana histórica',
        ]);

        OrderPromotionItem::query()->create([
            'order_item_id' => $promotionOrderItem->id,
            'pizza_id' => $catalog['pizza_c_id'],
            'pizza_name' => 'Pepperoni histórica',
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/products'
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-03'
                    .'&timezone=America/Guayaquil',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.summary.total_pizza_units',
                8,
            )
            ->assertJsonPath(
                'data.summary.unique_pizzas_sold',
                3,
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
                $catalog['pizza_a_id'],
            )
            ->assertJsonPath(
                'data.summary.top_pizza.pizza_name',
                'Americana histórica',
            )
            ->assertJsonPath(
                'data.summary.top_pizza.equivalent_units',
                5,
            )
            ->assertJsonPath(
                'data.summary.top_size.size_id',
                $catalog['small_id'],
            )
            ->assertJsonPath(
                'data.summary.top_size.pizza_units',
                4,
            )
            ->assertJsonPath(
                'data.summary.top_promotion.promotion_id',
                $catalog['promotion_id'],
            )
            ->assertJsonPath(
                'data.summary.top_promotion.packages_sold',
                2,
            )
            ->assertJsonCount(
                3,
                'data.pizzas',
            )
            ->assertJsonPath(
                'data.pizzas.0.pizza_id',
                $catalog['pizza_a_id'],
            )
            ->assertJsonPath(
                'data.pizzas.0.pizza_name',
                'Americana histórica',
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
                $catalog['pizza_c_id'],
            )
            ->assertJsonPath(
                'data.pizzas.1.equivalent_units',
                2,
            )
            ->assertJsonPath(
                'data.pizzas.1.complete_units',
                0,
            )
            ->assertJsonPath(
                'data.pizzas.1.half_units',
                0,
            )
            ->assertJsonPath(
                'data.pizzas.1.promotion_units',
                2,
            )
            ->assertJsonPath(
                'data.pizzas.2.pizza_id',
                $catalog['pizza_b_id'],
            )
            ->assertJsonPath(
                'data.pizzas.2.equivalent_units',
                1,
            )
            ->assertJsonPath(
                'data.pizzas.2.half_units',
                1,
            )
            ->assertJsonCount(
                1,
                'data.promotions',
            )
            ->assertJsonPath(
                'data.promotions.0.promotion_id',
                $catalog['promotion_id'],
            )
            ->assertJsonPath(
                'data.promotions.0.promotion_name',
                'Combo histórico',
            )
            ->assertJsonPath(
                'data.promotions.0.packages_sold',
                2,
            )
            ->assertJsonPath(
                'data.promotions.0.gross_sales',
                30,
            )
            ->assertJsonCount(
                3,
                'data.sizes',
            )
            ->assertJsonPath(
                'data.sizes.0.size_id',
                $catalog['small_id'],
            )
            ->assertJsonPath(
                'data.sizes.0.size_name',
                'Pequeña histórica',
            )
            ->assertJsonPath(
                'data.sizes.0.pizza_units',
                4,
            )
            ->assertJsonPath(
                'data.sizes.1.size_id',
                $catalog['large_id'],
            )
            ->assertJsonPath(
                'data.sizes.1.pizza_units',
                2,
            )
            ->assertJsonPath(
                'data.sizes.2.size_id',
                $catalog['medium_id'],
            )
            ->assertJsonPath(
                'data.sizes.2.pizza_units',
                2,
            );
    },
);

it(
    'excludes pending orders and delivered orders outside the requested period',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $catalog = createProductPerformanceCatalog();

        $pendingOrder = createProductPerformanceOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-PRODUCT-PENDING',
            orderedAt: '2026-08-02 10:00:00',
            statusId: $catalog['pending_status_id'],
            total: '50.00',
        );

        OrderItem::query()->create([
            'order_id' => $pendingOrder->id,
            'promotion_id' => null,
            'promotion_name' => null,
            'pizza_id' => $catalog['pizza_a_id'],
            'pizza_name' => 'Pizza pendiente',
            'pizza_id_second' => null,
            'pizza_name_second' => null,
            'size_id' => $catalog['medium_id'],
            'size_name' => 'Mediana pendiente',
            'category_name' => 'Especiales',
            'category_name_second' => null,
            'is_half_and_half' => false,
            'quantity' => 5,
            'unit_price' => '10.00',
            'subtotal' => '50.00',
        ]);

        $outsideOrder = createProductPerformanceOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-PRODUCT-OUTSIDE',
            orderedAt: '2026-07-31 23:59:59',
            statusId: $catalog['delivered_status_id'],
            total: '40.00',
        );

        OrderItem::query()->create([
            'order_id' => $outsideOrder->id,
            'promotion_id' => null,
            'promotion_name' => null,
            'pizza_id' => $catalog['pizza_b_id'],
            'pizza_name' => 'Pizza fuera del rango',
            'pizza_id_second' => null,
            'pizza_name_second' => null,
            'size_id' => $catalog['large_id'],
            'size_name' => 'Familiar fuera del rango',
            'category_name' => 'Especiales',
            'category_name_second' => null,
            'is_half_and_half' => false,
            'quantity' => 4,
            'unit_price' => '10.00',
            'subtotal' => '40.00',
        ]);

        $includedOrder = createProductPerformanceOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-PRODUCT-INCLUDED',
            orderedAt: '2026-08-01 00:00:00',
            statusId: $catalog['delivered_status_id'],
            total: '10.00',
        );

        OrderItem::query()->create([
            'order_id' => $includedOrder->id,
            'promotion_id' => null,
            'promotion_name' => null,
            'pizza_id' => $catalog['pizza_c_id'],
            'pizza_name' => 'Pizza incluida',
            'pizza_id_second' => null,
            'pizza_name_second' => null,
            'size_id' => $catalog['small_id'],
            'size_name' => 'Pequeña incluida',
            'category_name' => 'Especiales',
            'category_name_second' => null,
            'is_half_and_half' => false,
            'quantity' => 1,
            'unit_price' => '10.00',
            'subtotal' => '10.00',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/products'
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-02'
                    .'&timezone=America/Guayaquil',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_pizza_units',
                1,
            )
            ->assertJsonPath(
                'data.summary.unique_pizzas_sold',
                1,
            )
            ->assertJsonPath(
                'data.pizzas.0.pizza_id',
                $catalog['pizza_c_id'],
            )
            ->assertJsonPath(
                'data.pizzas.0.pizza_name',
                'Pizza incluida',
            )
            ->assertJsonPath(
                'data.pizzas.0.equivalent_units',
                1,
            )
            ->assertJsonPath(
                'data.sizes.0.size_id',
                $catalog['small_id'],
            )
            ->assertJsonPath(
                'data.sizes.0.pizza_units',
                1,
            )
            ->assertJsonPath(
                'data.promotions',
                [],
            );
    },
);

it(
    'uses historical snapshots instead of current product names and prices',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $catalog = createProductPerformanceCatalog();

        $order = createProductPerformanceOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-PRODUCT-HISTORY',
            orderedAt: '2026-08-02 16:00:00',
            statusId: $catalog['delivered_status_id'],
            total: '24.00',
        );

        $orderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'promotion_id' => $catalog['promotion_id'],
            'promotion_name' => 'Nombre histórico del combo',
            'pizza_id' => null,
            'pizza_name' => null,
            'pizza_id_second' => null,
            'pizza_name_second' => null,
            'size_id' => $catalog['medium_id'],
            'size_name' => 'Tamaño histórico',
            'category_name' => null,
            'category_name_second' => null,
            'is_half_and_half' => false,
            'quantity' => 2,
            'unit_price' => '12.00',
            'subtotal' => '24.00',
        ]);

        OrderPromotionItem::query()->create([
            'order_item_id' => $orderItem->id,
            'pizza_id' => $catalog['pizza_a_id'],
            'pizza_name' => 'Pizza histórica del paquete',
        ]);

        Promotion::query()
            ->whereKey(
                $catalog['promotion_id'],
            )
            ->update([
                'promotion_name' => 'Nombre actual diferente',
                'promotion_price' => '99.00',
            ]);

        Pizza::query()
            ->whereKey(
                $catalog['pizza_a_id'],
            )
            ->update([
                'pizza_name' => 'Pizza actual diferente',
            ]);

        Size::query()
            ->whereKey(
                $catalog['medium_id'],
            )
            ->update([
                'size_name' => 'Tamaño actual diferente',
            ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/products'
                    .'?date_from=2026-08-02'
                    .'&date_to=2026-08-02',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.pizzas.0.pizza_name',
                'Pizza histórica del paquete',
            )
            ->assertJsonPath(
                'data.promotions.0.promotion_name',
                'Nombre histórico del combo',
            )
            ->assertJsonPath(
                'data.promotions.0.packages_sold',
                2,
            )
            ->assertJsonPath(
                'data.promotions.0.gross_sales',
                24,
            )
            ->assertJsonPath(
                'data.sizes.0.size_name',
                'Tamaño histórico',
            );
    },
);
