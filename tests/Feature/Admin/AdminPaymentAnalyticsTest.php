<?php

declare(strict_types=1);

use App\Enums\PaymentReceiptStatus;
use App\Enums\PaymentStatus;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderStatusChange;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     delivered_status_id: int,
 *     pending_status_id: int,
 *     cash_method_id: int,
 *     transfer_method_id: int,
 *     card_method_id: int,
 *     delivery_type_id: int
 * }
 */
function paymentAnalyticsCatalog(): array
{
    $deliveredStatus = OrderStatus::query()
        ->firstOrCreate([
            'status_name' => 'delivered',
        ]);

    $pendingStatus = OrderStatus::query()
        ->firstOrCreate([
            'status_name' => 'pending',
        ]);

    $cashMethod = PaymentMethod::query()
        ->firstOrCreate(
            [
                'name' => 'cash',
            ],
            [
                'description' => 'Pago en efectivo',
                'active' => true,
            ],
        );

    $transferMethod = PaymentMethod::query()
        ->firstOrCreate(
            [
                'name' => 'transfer',
            ],
            [
                'description' => 'Transferencia bancaria',
                'active' => true,
            ],
        );

    $cardMethod = PaymentMethod::query()
        ->firstOrCreate(
            [
                'name' => 'card',
            ],
            [
                'description' => 'Pago mediante PayPal',
                'active' => true,
            ],
        );

    $deliveryType = DeliveryType::query()
        ->firstOrCreate([
            'delivery_type_name' => 'pickup',
        ]);

    return [
        'delivered_status_id' => (int) $deliveredStatus->id,
        'pending_status_id' => (int) $pendingStatus->id,
        'cash_method_id' => (int) $cashMethod->id,
        'transfer_method_id' => (int) $transferMethod->id,
        'card_method_id' => (int) $cardMethod->id,
        'delivery_type_id' => (int) $deliveryType->id,
    ];
}

/**
 * @param  array<string, int>  $catalog
 */
function createPaymentAnalyticsOrder(
    User $customer,
    array $catalog,
    string $number,
    int $paymentMethodId,
    string $total,
    string $orderedAt = '2026-08-01 12:00:00',
): Order {
    return Order::query()->create([
        'order_number' => $number,
        'user_id' => $customer->id,
        'ordered_at' => $orderedAt,
        'subtotal' => $total,
        'delivery_fee' => '0.00',
        'total' => $total,
        'delivery_type_id' => $catalog[
            'delivery_type_id'
        ],
        'address' => null,
        'delivery_lat' => null,
        'delivery_lng' => null,
        'delivery_maps_url' => null,
        'delivery_place_id' => null,
        'delivery_reference' => null,
        'payment_method_id' => $paymentMethodId,
        'order_status_id' => $catalog[
            'delivered_status_id'
        ],
    ]);
}

function createPaymentAnalyticsReceipt(
    Order $order,
    User $customer,
    PaymentReceiptStatus $status,
    string $submittedAt,
    ?string $reviewedAt = null,
    ?User $reviewer = null,
): PaymentReceipt {
    return PaymentReceipt::query()->create([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'user_id' => $customer->id,
        'disk' => 'payment_receipts',
        'file_path' => 'tests/'.Str::uuid().'.jpg',
        'original_name' => 'comprobante.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 1024,
        'status' => $status,
        'rejection_reason' => $status
            === PaymentReceiptStatus::Rejected
                ? 'Comprobante rechazado.'
                : null,
        'submitted_at' => $submittedAt,
        'reviewed_at' => $reviewedAt,
        'reviewed_by' => $reviewer?->id,
        'expires_at' => null,
        'file_deleted_at' => null,
    ]);
}

function createPaymentAnalyticsPayment(
    User $customer,
    PaymentStatus $status,
    string $amount,
    array $timestamps = [],
): Payment {
    return Payment::query()->forceCreate([
        'uuid' => (string) Str::uuid(),
        'idempotency_key' => (string) Str::uuid(),
        'user_id' => $customer->id,
        'cart_id' => null,
        'order_id' => null,
        'provider' => 'paypal',
        'provider_order_id' => 'PAYPAL-ORDER-'.Str::uuid(),
        'provider_capture_id' => $status === PaymentStatus::COMPLETED
            ? 'PAYPAL-CAPTURE-'.Str::uuid()
            : null,
        'provider_status' => strtoupper(
            $status->value,
        ),
        'amount' => $amount,
        'currency' => 'USD',
        'status' => $status,
        'failure_code' => null,
        'failure_message' => null,
        'provider_metadata' => null,
        'checkout_context' => null,
        'cart_fingerprint' => null,
        'approved_at' => null,
        'paid_at' => null,
        'failed_at' => null,
        'cancelled_at' => null,
        'refunded_at' => null,
        'created_at' => '2026-08-01 00:00:00',
        'updated_at' => '2026-08-01 00:00:00',
        ...$timestamps,
    ]);
}

it(
    'requires authentication to access payment analytics',
    function (): void {
        /** @var TestCase $this */
        $this
            ->getJson(
                '/api/v1/admin/analytics/payments',
            )
            ->assertUnauthorized();
    },
);

it(
    'forbids non administrators from accessing payment analytics',
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
                '/api/v1/admin/analytics/payments',
            )
            ->assertForbidden();

        $this
            ->actingAs(
                $operator,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/payments',
            )
            ->assertForbidden();
    },
);

it(
    'validates the payment analytics date range and timezone',
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
                '/api/v1/admin/analytics/payments'
                    .'?date_from=2025-01-01'
                    .'&date_to=2026-08-01'
                    .'&timezone=UTC',
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
    'returns an empty financial report when there are no movements',
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
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-03'
                    .'&timezone=UTC',
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'message',
                'Resumen financiero recuperado correctamente.',
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
                'UTC',
            )
            ->assertJsonPath(
                'data.period.days',
                3,
            )
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
                'data.summary.refunded_payments',
                0,
            )
            ->assertJsonPath(
                'data.summary.partially_refunded_payments',
                0,
            )
            ->assertJsonPath(
                'data.refunds.refundable_amount_available',
                false,
            )
            ->assertJsonCount(
                3,
                'data.methods',
            )
            ->assertJsonPath(
                'data.methods.0.method',
                'cash',
            )
            ->assertJsonPath(
                'data.methods.1.method',
                'transfer',
            )
            ->assertJsonPath(
                'data.methods.2.method',
                'paypal',
            );
    },
);

it(
    'recognizes collected income using the financial date of each method',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $catalog = paymentAnalyticsCatalog();

        /*
         * El pedido se creó antes del rango, pero el efectivo se
         * reconoce al momento de la entrega.
         */
        $cashOrder = createPaymentAnalyticsOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-PAYMENT-CASH-001',
            paymentMethodId: $catalog[
                'cash_method_id'
            ],
            total: '20.00',
            orderedAt: '2026-07-31 18:00:00',
        );

        OrderStatusChange::query()->create([
            'order_id' => $cashOrder->id,
            'from_order_status_id' => null,
            'to_order_status_id' => $catalog[
                'delivered_status_id'
            ],
            'changed_by_user_id' => $admin->id,
            'changed_at' => '2026-08-01 10:00:00',
            'note' => 'Cobro en efectivo.',
        ]);

        /*
         * Dos comprobantes aprobados para el mismo pedido no deben
         * duplicar ni el pedido ni su importe.
         */
        $transferOrder = createPaymentAnalyticsOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-PAYMENT-TRANSFER-001',
            paymentMethodId: $catalog[
                'transfer_method_id'
            ],
            total: '30.00',
        );

        createPaymentAnalyticsReceipt(
            order: $transferOrder,
            customer: $customer,
            status: PaymentReceiptStatus::Approved,
            submittedAt: '2026-08-01 11:00:00',
            reviewedAt: '2026-08-01 11:10:00',
            reviewer: $admin,
        );

        createPaymentAnalyticsReceipt(
            order: $transferOrder,
            customer: $customer,
            status: PaymentReceiptStatus::Approved,
            submittedAt: '2026-08-01 11:20:00',
            reviewedAt: '2026-08-01 11:30:00',
            reviewer: $admin,
        );

        /*
         * PayPal se reconoce por paid_at, no por created_at.
         */
        createPaymentAnalyticsPayment(
            customer: $customer,
            status: PaymentStatus::COMPLETED,
            amount: '40.00',
            timestamps: [
                'paid_at' => '2026-08-01 12:00:00',
                'created_at' => '2026-07-30 12:00:00',
                'updated_at' => '2026-08-01 12:00:00',
            ],
        );

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/payments'
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-01'
                    .'&timezone=UTC',
            );

        $response
            ->assertOk()
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
                'data.methods.0.method',
                'cash',
            )
            ->assertJsonPath(
                'data.methods.0.label',
                'Efectivo',
            )
            ->assertJsonPath(
                'data.methods.0.amount',
                20,
            )
            ->assertJsonPath(
                'data.methods.0.orders',
                1,
            )
            ->assertJsonPath(
                'data.methods.1.method',
                'transfer',
            )
            ->assertJsonPath(
                'data.methods.1.amount',
                30,
            )
            ->assertJsonPath(
                'data.methods.1.orders',
                1,
            )
            ->assertJsonPath(
                'data.methods.2.method',
                'paypal',
            )
            ->assertJsonPath(
                'data.methods.2.amount',
                40,
            )
            ->assertJsonPath(
                'data.methods.2.payments',
                1,
            );
    },
);

it(
    'counts only the latest pending transfer receipt and pending paypal states',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $catalog = paymentAnalyticsCatalog();

        /*
         * Este pedido no debe quedar pendiente porque su comprobante
         * más reciente fue rechazado.
         */
        $resolvedTransfer = createPaymentAnalyticsOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-PENDING-RESOLVED',
            paymentMethodId: $catalog[
                'transfer_method_id'
            ],
            total: '25.00',
        );

        createPaymentAnalyticsReceipt(
            order: $resolvedTransfer,
            customer: $customer,
            status: PaymentReceiptStatus::Pending,
            submittedAt: '2026-08-01 08:00:00',
        );

        createPaymentAnalyticsReceipt(
            order: $resolvedTransfer,
            customer: $customer,
            status: PaymentReceiptStatus::Rejected,
            submittedAt: '2026-08-01 08:30:00',
            reviewedAt: '2026-08-01 08:40:00',
            reviewer: $admin,
        );

        /*
         * Este pedido sí debe quedar pendiente porque su último
         * comprobante está pendiente.
         */
        $pendingTransfer = createPaymentAnalyticsOrder(
            customer: $customer,
            catalog: $catalog,
            number: 'CH-PENDING-TRANSFER',
            paymentMethodId: $catalog[
                'transfer_method_id'
            ],
            total: '35.00',
        );

        createPaymentAnalyticsReceipt(
            order: $pendingTransfer,
            customer: $customer,
            status: PaymentReceiptStatus::Rejected,
            submittedAt: '2026-08-01 09:00:00',
            reviewedAt: '2026-08-01 09:10:00',
            reviewer: $admin,
        );

        createPaymentAnalyticsReceipt(
            order: $pendingTransfer,
            customer: $customer,
            status: PaymentReceiptStatus::Pending,
            submittedAt: '2026-08-01 09:30:00',
        );

        createPaymentAnalyticsPayment(
            customer: $customer,
            status: PaymentStatus::CREATED,
            amount: '10.00',
            timestamps: [
                'created_at' => '2026-08-01 10:00:00',
                'updated_at' => '2026-08-01 10:00:00',
            ],
        );

        createPaymentAnalyticsPayment(
            customer: $customer,
            status: PaymentStatus::PENDING,
            amount: '15.00',
            timestamps: [
                'created_at' => '2026-08-01 10:30:00',
                'updated_at' => '2026-08-01 10:30:00',
            ],
        );

        createPaymentAnalyticsPayment(
            customer: $customer,
            status: PaymentStatus::APPROVED,
            amount: '20.00',
            timestamps: [
                'approved_at' => '2026-08-01 11:00:00',
                'created_at' => '2026-08-01 10:45:00',
                'updated_at' => '2026-08-01 11:00:00',
            ],
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/payments'
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-01'
                    .'&timezone=UTC',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.pending.transfer.amount',
                35,
            )
            ->assertJsonPath(
                'data.pending.transfer.transactions',
                1,
            )
            ->assertJsonPath(
                'data.pending.paypal.amount',
                45,
            )
            ->assertJsonPath(
                'data.pending.paypal.transactions',
                3,
            )
            ->assertJsonPath(
                'data.pending.amount',
                80,
            )
            ->assertJsonPath(
                'data.pending.transactions',
                4,
            )
            ->assertJsonPath(
                'data.summary.pending_amount',
                80,
            )
            ->assertJsonPath(
                'data.summary.pending_transactions',
                4,
            );
    },
);

it(
    'excludes failed denied cancelled and out of range paypal operations',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        createPaymentAnalyticsPayment(
            customer: $customer,
            status: PaymentStatus::FAILED,
            amount: '50.00',
            timestamps: [
                'failed_at' => '2026-08-01 08:00:00',
                'created_at' => '2026-08-01 07:00:00',
                'updated_at' => '2026-08-01 08:00:00',
            ],
        );

        createPaymentAnalyticsPayment(
            customer: $customer,
            status: PaymentStatus::DENIED,
            amount: '60.00',
            timestamps: [
                'failed_at' => '2026-08-01 09:00:00',
                'created_at' => '2026-08-01 08:30:00',
                'updated_at' => '2026-08-01 09:00:00',
            ],
        );

        createPaymentAnalyticsPayment(
            customer: $customer,
            status: PaymentStatus::CANCELLED,
            amount: '70.00',
            timestamps: [
                'cancelled_at' => '2026-08-01 10:00:00',
                'created_at' => '2026-08-01 09:30:00',
                'updated_at' => '2026-08-01 10:00:00',
            ],
        );

        createPaymentAnalyticsPayment(
            customer: $customer,
            status: PaymentStatus::COMPLETED,
            amount: '80.00',
            timestamps: [
                'paid_at' => '2026-07-31 23:59:59',
                'created_at' => '2026-07-31 23:00:00',
                'updated_at' => '2026-07-31 23:59:59',
            ],
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/payments'
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-01'
                    .'&timezone=UTC',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.collected_total',
                0,
            )
            ->assertJsonPath(
                'data.summary.paypal_amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.paypal_payments',
                0,
            )
            ->assertJsonPath(
                'data.summary.pending_amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.pending_transactions',
                0,
            );
    },
);

it(
    'counts full and partial refunds by refunded date without inventing amounts',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        createPaymentAnalyticsPayment(
            customer: $customer,
            status: PaymentStatus::REFUNDED,
            amount: '30.00',
            timestamps: [
                'paid_at' => '2026-07-20 10:00:00',
                'refunded_at' => '2026-08-01 10:00:00',
                'created_at' => '2026-07-20 09:00:00',
                'updated_at' => '2026-08-01 10:00:00',
            ],
        );

        createPaymentAnalyticsPayment(
            customer: $customer,
            status: PaymentStatus::REFUNDED,
            amount: '40.00',
            timestamps: [
                'paid_at' => '2026-07-21 10:00:00',
                'refunded_at' => '2026-08-02 10:00:00',
                'created_at' => '2026-07-21 09:00:00',
                'updated_at' => '2026-08-02 10:00:00',
            ],
        );

        createPaymentAnalyticsPayment(
            customer: $customer,
            status: PaymentStatus::PARTIALLY_REFUNDED,
            amount: '50.00',
            timestamps: [
                'paid_at' => '2026-07-22 10:00:00',
                'refunded_at' => '2026-08-02 11:00:00',
                'created_at' => '2026-07-22 09:00:00',
                'updated_at' => '2026-08-02 11:00:00',
            ],
        );

        /*
         * Fuera del rango: no debe contarse.
         */
        createPaymentAnalyticsPayment(
            customer: $customer,
            status: PaymentStatus::REFUNDED,
            amount: '99.00',
            timestamps: [
                'paid_at' => '2026-07-01 10:00:00',
                'refunded_at' => '2026-07-31 23:59:59',
                'created_at' => '2026-07-01 09:00:00',
                'updated_at' => '2026-07-31 23:59:59',
            ],
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/payments'
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-02'
                    .'&timezone=UTC',
            )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.refunded_payments',
                2,
            )
            ->assertJsonPath(
                'data.summary.partially_refunded_payments',
                1,
            )
            ->assertJsonPath(
                'data.refunds.refunded_payments',
                2,
            )
            ->assertJsonPath(
                'data.refunds.partially_refunded_payments',
                1,
            )
            ->assertJsonPath(
                'data.refunds.refundable_amount_available',
                false,
            )
            ->assertJsonMissingPath(
                'data.refunds.amount',
            )
            ->assertJsonMissingPath(
                'data.summary.refunded_amount',
            );
    },
);
