<?php

declare(strict_types=1);

use App\Enums\PaymentReceiptStatus;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\PaymentReceipt;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake(
        'payment_receipts',
    );
});
/**
 * Crea una imagen JPEG falsa sin depender de la extensión GD.
 */
function fakePaymentReceiptImage(
    string $name = 'comprobante.jpg',
    int $kilobytes = 100,
): UploadedFile {
    return UploadedFile::fake()->create(
        $name,
        $kilobytes,
        'image/jpeg',
    );
}

/**
 * Crea un usuario activo con el rol solicitado.
 */
function paymentReceiptUser(
    string $roleName,
): User {
    $role = Role::query()
        ->where(
            'role_name',
            $roleName,
        )
        ->firstOrFail();

    return User::factory()->create([
        'role_id' => (int) $role->id,

        'is_active' => true,
    ]);
}

/**
 * Crea un pedido mínimo válido para las pruebas.
 */
function paymentReceiptOrder(
    User $customer,
    string $paymentMethod = 'transfer',
    string $status = 'pending',
): Order {
    $deliveryType = DeliveryType::query()
        ->where(
            'delivery_type_name',
            'pickup',
        )
        ->firstOrFail();

    $method = PaymentMethod::query()
        ->where(
            'name',
            $paymentMethod,
        )
        ->firstOrFail();

    $orderStatus = OrderStatus::query()
        ->where(
            'status_name',
            $status,
        )
        ->firstOrFail();

    return Order::query()->create([
        'order_number' => 'CH-TEST-'.Str::upper(
            Str::random(10),
        ),

        'user_id' => (int) $customer->id,

        'ordered_at' => now(),

        'subtotal' => 10.00,

        'delivery_fee' => 0.00,

        'total' => 10.00,

        'delivery_type_id' => (int) $deliveryType->id,

        'address' => null,

        'delivery_lat' => null,

        'delivery_lng' => null,

        'delivery_maps_url' => null,

        'delivery_place_id' => null,

        'delivery_reference' => null,

        'payment_method_id' => (int) $method->id,

        'order_status_id' => (int) $orderStatus->id,

        'whatsapp_receipt_url' => null,
    ]);
}

describe(
    'Comprobantes de transferencia',
    function (): void {
        it(
            'requiere autenticación para subir un comprobante',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $this
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'comprobante.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertUnauthorized();

                $this->assertDatabaseCount(
                    'payment_receipts',
                    0,
                );
            },
        );

        it(
            'permite al dueño subir un comprobante privado',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $response = $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'transferencia.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    );

                $response
                    ->assertCreated()
                    ->assertJsonPath(
                        'data.order_id',
                        (int) $order->id,
                    )
                    ->assertJsonPath(
                        'data.status',
                        'pending',
                    )
                    ->assertJsonPath(
                        'data.original_name',
                        'transferencia.jpg',
                    )
                    ->assertJsonPath(
                        'data.mime_type',
                        'image/jpeg',
                    )
                    ->assertJsonPath(
                        'data.file_available',
                        true,
                    )
                    ->assertJsonPath(
                        'data.rejection_reason',
                        null,
                    );

                $receipt = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->firstOrFail();

                expect(
                    $receipt->status,
                )->toBe(
                    PaymentReceiptStatus::Pending,
                );

                expect(
                    $receipt->file_path,
                )->not->toBeNull();

                $this->assertTrue(
                    Storage::disk(
                        'payment_receipts',
                    )->exists(
                        (string) $receipt->file_path,
                    ),
                    'El comprobante debía existir en el disco privado.',
                );
            },
        );

        it(
            'impide subir un comprobante a un pedido ajeno',
            function (): void {
                /** @var \Tests\TestCase $this */
                $owner = paymentReceiptUser(
                    'customer',
                );

                $otherCustomer = paymentReceiptUser(
                    'customer',
                );

                $order = paymentReceiptOrder(
                    $owner,
                );

                $this
                    ->actingAs(
                        $otherCustomer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'comprobante.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertNotFound();

                $this->assertDatabaseCount(
                    'payment_receipts',
                    0,
                );
            },
        );

        it(
            'solo acepta comprobantes para pedidos por transferencia',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $order = paymentReceiptOrder(
                    customer: $customer,
                    paymentMethod: 'cash',
                );

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'comprobante.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors([
                        'receipt',
                    ]);

                $this->assertDatabaseCount(
                    'payment_receipts',
                    0,
                );
            },
        );

        it(
            'rechaza formatos no permitidos',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => UploadedFile::fake()->create(
                                'archivo.exe',
                                100,
                                'application/x-msdownload',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors([
                        'receipt',
                    ]);

                $this->assertDatabaseCount(
                    'payment_receipts',
                    0,
                );
            },
        );

        it(
            'rechaza archivos mayores a cinco megabytes',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => UploadedFile::fake()->create(
                                'comprobante.pdf',
                                5121,
                                'application/pdf',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors([
                        'receipt',
                    ]);

                $this->assertDatabaseCount(
                    'payment_receipts',
                    0,
                );
            },
        );

        it(
            'impide tener dos comprobantes pendientes',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $url =
                    "/api/v1/my/orders/{$order->id}/payment-receipts";

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        $url,
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'primero.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        $url,
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'segundo.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors([
                        'receipt',
                    ]);

                $this->assertDatabaseCount(
                    'payment_receipts',
                    1,
                );
            },
        );

        it(
            'permite al operador aprobar un comprobante',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $operator = paymentReceiptUser(
                    'operator',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'comprobante.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $receipt = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->firstOrFail();

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->patchJson(
                        "/api/v1/operator/payment-receipts/{$receipt->uuid}/approve",
                    )
                    ->assertOk()
                    ->assertJsonPath(
                        'data.status',
                        'approved',
                    )
                    ->assertJsonPath(
                        'data.rejection_reason',
                        null,
                    );

                $receipt->refresh();

                expect(
                    $receipt->status,
                )->toBe(
                    PaymentReceiptStatus::Approved,
                );

                expect(
                    (int) $receipt->reviewed_by,
                )->toBe(
                    (int) $operator->id,
                );

                expect(
                    $receipt->reviewed_at,
                )->not->toBeNull();

                expect(
                    $receipt->expires_at,
                )->not->toBeNull();
            },
        );

        it(
            'permite al administrador aprobar un comprobante',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $admin = paymentReceiptUser(
                    'admin',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'comprobante-admin.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $receipt = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->firstOrFail();

                $this
                    ->actingAs(
                        $admin,
                        'sanctum',
                    )
                    ->patchJson(
                        "/api/v1/operator/payment-receipts/{$receipt->uuid}/approve",
                    )
                    ->assertOk()
                    ->assertJsonPath(
                        'data.status',
                        'approved',
                    );

                $receipt->refresh();

                expect(
                    (int) $receipt->reviewed_by,
                )->toBe(
                    (int) $admin->id,
                );
            },
        );

        it(
            'impide aprobar un comprobante de un pedido cancelado',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $operator = paymentReceiptUser(
                    'operator',
                );

                $order = paymentReceiptOrder(
                    customer: $customer,
                );

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'comprobante-cancelado.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $receipt = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->firstOrFail();

                $cancelledStatus = OrderStatus::query()
                    ->where(
                        'status_name',
                        'cancelled',
                    )
                    ->firstOrFail();

                $order->forceFill([
                    'order_status_id' => (int) $cancelledStatus->id,
                ])->save();

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->patchJson(
                        "/api/v1/operator/payment-receipts/{$receipt->uuid}/approve",
                    )
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors([
                        'receipt',
                    ])
                    ->assertJsonPath(
                        'errors.receipt.0',
                        'No se puede revisar un comprobante de un pedido finalizado o cancelado.',
                    );

                $receipt->refresh();

                expect(
                    $receipt->status,
                )->toBe(
                    PaymentReceiptStatus::Pending,
                );

                expect(
                    $receipt->reviewed_at,
                )->toBeNull();

                expect(
                    $receipt->reviewed_by,
                )->toBeNull();
            },
        );

        it(
            'impide que un cliente apruebe un comprobante',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'comprobante.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $receipt = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->firstOrFail();

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->patchJson(
                        "/api/v1/operator/payment-receipts/{$receipt->uuid}/approve",
                    )
                    ->assertForbidden();

                $receipt->refresh();

                expect(
                    $receipt->status,
                )->toBe(
                    PaymentReceiptStatus::Pending,
                );
            },
        );

        it(
            'permite al operador rechazar con un motivo',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $operator = paymentReceiptUser(
                    'operator',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'comprobante.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $receipt = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->firstOrFail();

                $reason =
                    'El valor transferido no coincide con el total.';

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->patchJson(
                        "/api/v1/operator/payment-receipts/{$receipt->uuid}/reject",
                        [
                            'reason' => $reason,
                        ],
                    )
                    ->assertOk()
                    ->assertJsonPath(
                        'data.status',
                        'rejected',
                    )
                    ->assertJsonPath(
                        'data.rejection_reason',
                        $reason,
                    );

                $receipt->refresh();

                expect(
                    $receipt->status,
                )->toBe(
                    PaymentReceiptStatus::Rejected,
                );

                expect(
                    $receipt->rejection_reason,
                )->toBe(
                    $reason,
                );

                expect(
                    (int) $receipt->reviewed_by,
                )->toBe(
                    (int) $operator->id,
                );

                expect(
                    $receipt->expires_at,
                )->not->toBeNull();
            },
        );

        it(
            'impide rechazar un comprobante de un pedido entregado',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $operator = paymentReceiptUser(
                    'operator',
                );

                $order = paymentReceiptOrder(
                    customer: $customer,
                );

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'comprobante-entregado.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $receipt = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->firstOrFail();

                $deliveredStatus = OrderStatus::query()
                    ->where(
                        'status_name',
                        'delivered',
                    )
                    ->firstOrFail();

                $order->forceFill([
                    'order_status_id' => (int) $deliveredStatus->id,
                ])->save();

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->patchJson(
                        "/api/v1/operator/payment-receipts/{$receipt->uuid}/reject",
                        [
                            'reason' => 'El valor transferido no coincide.',
                        ],
                    )
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors([
                        'receipt',
                    ])
                    ->assertJsonPath(
                        'errors.receipt.0',
                        'No se puede revisar un comprobante de un pedido finalizado o cancelado.',
                    );

                $receipt->refresh();

                expect(
                    $receipt->status,
                )->toBe(
                    PaymentReceiptStatus::Pending,
                );

                expect(
                    $receipt->rejection_reason,
                )->toBeNull();

                expect(
                    $receipt->reviewed_at,
                )->toBeNull();

                expect(
                    $receipt->reviewed_by,
                )->toBeNull();
            },
        );

        it(
            'exige un motivo válido para rechazar',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $operator = paymentReceiptUser(
                    'operator',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'comprobante.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $receipt = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->firstOrFail();

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->patchJson(
                        "/api/v1/operator/payment-receipts/{$receipt->uuid}/reject",
                        [
                            'reason' => '',
                        ],
                    )
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors([
                        'reason',
                    ]);

                $receipt->refresh();

                expect(
                    $receipt->status,
                )->toBe(
                    PaymentReceiptStatus::Pending,
                );
            },
        );

        it(
            'permite volver a subir después de un rechazo',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $operator = paymentReceiptUser(
                    'operator',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $url =
                    "/api/v1/my/orders/{$order->id}/payment-receipts";

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        $url,
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'primero.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $firstReceipt = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->firstOrFail();

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->patchJson(
                        "/api/v1/operator/payment-receipts/{$firstReceipt->uuid}/reject",
                        [
                            'reason' => 'El comprobante no es legible.',
                        ],
                    )
                    ->assertOk();

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        $url,
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'segundo.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated()
                    ->assertJsonPath(
                        'data.status',
                        'pending',
                    )
                    ->assertJsonPath(
                        'data.original_name',
                        'segundo.jpg',
                    );

                $this->assertDatabaseCount(
                    'payment_receipts',
                    2,
                );

                $latest = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->latest(
                        'submitted_at',
                    )
                    ->latest('id')
                    ->firstOrFail();

                expect(
                    $latest->status,
                )->toBe(
                    PaymentReceiptStatus::Pending,
                );
            },
        );

        it(
            'impide subir otro comprobante después de aprobarlo',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $operator = paymentReceiptUser(
                    'operator',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $url =
                    "/api/v1/my/orders/{$order->id}/payment-receipts";

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        $url,
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'aprobable.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $receipt = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->firstOrFail();

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->patchJson(
                        "/api/v1/operator/payment-receipts/{$receipt->uuid}/approve",
                    )
                    ->assertOk();

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        $url,
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'otro.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors([
                        'receipt',
                    ]);

                $this->assertDatabaseCount(
                    'payment_receipts',
                    1,
                );
            },
        );

        it(
            'devuelve el comprobante más reciente del pedido',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'comprobante.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->getJson(
                        "/api/v1/my/orders/{$order->id}/payment-receipts/latest",
                    )
                    ->assertOk()
                    ->assertJsonPath(
                        'data.order_id',
                        (int) $order->id,
                    )
                    ->assertJsonPath(
                        'data.status',
                        'pending',
                    )
                    ->assertJsonPath(
                        'data.original_name',
                        'comprobante.jpg',
                    );
            },
        );

        it(
            'devuelve el historial de comprobantes del pedido',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $operator = paymentReceiptUser(
                    'operator',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $url =
                    "/api/v1/my/orders/{$order->id}/payment-receipts";

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        $url,
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'primero.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $firstReceipt = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->firstOrFail();

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->patchJson(
                        "/api/v1/operator/payment-receipts/{$firstReceipt->uuid}/reject",
                        [
                            'reason' => 'La imagen no es legible.',
                        ],
                    )
                    ->assertOk();

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        $url,
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'segundo.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->getJson($url)
                    ->assertOk()
                    ->assertJsonCount(
                        2,
                        'data',
                    )
                    ->assertJsonPath(
                        'data.0.original_name',
                        'segundo.jpg',
                    )
                    ->assertJsonPath(
                        'data.0.status',
                        'pending',
                    )
                    ->assertJsonPath(
                        'data.1.original_name',
                        'primero.jpg',
                    )
                    ->assertJsonPath(
                        'data.1.status',
                        'rejected',
                    );
            },
        );

        it(
            'impide consultar el historial de un pedido ajeno',
            function (): void {
                /** @var \Tests\TestCase $this */
                $owner = paymentReceiptUser(
                    'customer',
                );

                $otherCustomer = paymentReceiptUser(
                    'customer',
                );

                $order = paymentReceiptOrder(
                    $owner,
                );

                $this
                    ->actingAs(
                        $otherCustomer,
                        'sanctum',
                    )
                    ->getJson(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                    )
                    ->assertNotFound();
            },
        );

        it(
            'permite al dueño visualizar el archivo privado',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'comprobante.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $receipt = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->firstOrFail();

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->get(
                        "/api/v1/payment-receipts/{$receipt->uuid}/file",
                        [
                            'Accept' => 'image/jpeg',
                        ],
                    )
                    ->assertOk()
                    ->assertHeader(
                        'Content-Type',
                        'image/jpeg',
                    )
                    ->assertHeader(
                        'X-Content-Type-Options',
                        'nosniff',
                    );
            },
        );

        it(
            'impide visualizar el archivo de otro cliente',
            function (): void {
                /** @var \Tests\TestCase $this */
                $owner = paymentReceiptUser(
                    'customer',
                );

                $otherCustomer = paymentReceiptUser(
                    'customer',
                );

                $order = paymentReceiptOrder(
                    $owner,
                );

                $this
                    ->actingAs(
                        $owner,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'comprobante.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $receipt = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->firstOrFail();

                $this
                    ->actingAs(
                        $otherCustomer,
                        'sanctum',
                    )
                    ->get(
                        "/api/v1/payment-receipts/{$receipt->uuid}/file",
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertForbidden();
            },
        );

        it(
            'lista los comprobantes pendientes para el operador',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $operator = paymentReceiptUser(
                    'operator',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'pendiente.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->getJson(
                        '/api/v1/operator/payment-receipts',
                    )
                    ->assertOk()
                    ->assertJsonCount(
                        1,
                        'data',
                    )
                    ->assertJsonPath(
                        'data.0.status',
                        'pending',
                    )
                    ->assertJsonPath(
                        'data.0.order.order_number',
                        $order->order_number,
                    );
            },
        );

        it(
            'impide aprobar dos veces el mismo comprobante',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $operator = paymentReceiptUser(
                    'operator',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'comprobante.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $receipt = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->firstOrFail();

                $url = "/api/v1/operator/payment-receipts/{$receipt->uuid}/approve";

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->patchJson($url)
                    ->assertOk()
                    ->assertJsonPath(
                        'data.status',
                        'approved',
                    );

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->patchJson($url)
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors([
                        'receipt',
                    ]);

                $receipt->refresh();

                expect(
                    $receipt->status,
                )->toBe(
                    PaymentReceiptStatus::Approved,
                );

                $this->assertNotNull(
                    $receipt->reviewed_at,
                );

                $this->assertSame(
                    (int) $operator->id,
                    (int) $receipt->reviewed_by,
                );
            },
        );

        it(
            'impide rechazar un comprobante ya aprobado',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $operator = paymentReceiptUser(
                    'operator',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'comprobante.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $receipt = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->firstOrFail();

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->patchJson(
                        "/api/v1/operator/payment-receipts/{$receipt->uuid}/approve",
                    )
                    ->assertOk();

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->patchJson(
                        "/api/v1/operator/payment-receipts/{$receipt->uuid}/reject",
                        [
                            'reason' => 'Intento de rechazo posterior.',
                        ],
                    )
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors([
                        'receipt',
                    ]);

                $receipt->refresh();

                expect(
                    $receipt->status,
                )->toBe(
                    PaymentReceiptStatus::Approved,
                );

                expect(
                    $receipt->rejection_reason,
                )->toBeNull();
            },
        );

        it(
            'impide aprobar un comprobante ya rechazado',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $operator = paymentReceiptUser(
                    'operator',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $this
                    ->actingAs(
                        $customer,
                        'sanctum',
                    )
                    ->post(
                        "/api/v1/my/orders/{$order->id}/payment-receipts",
                        [
                            'receipt' => fakePaymentReceiptImage(
                                'comprobante.jpg',
                            ),
                        ],
                        [
                            'Accept' => 'application/json',
                        ],
                    )
                    ->assertCreated();

                $receipt = PaymentReceipt::query()
                    ->where(
                        'order_id',
                        (int) $order->id,
                    )
                    ->firstOrFail();

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->patchJson(
                        "/api/v1/operator/payment-receipts/{$receipt->uuid}/reject",
                        [
                            'reason' => 'El comprobante no es legible.',
                        ],
                    )
                    ->assertOk()
                    ->assertJsonPath(
                        'data.status',
                        'rejected',
                    );

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->patchJson(
                        "/api/v1/operator/payment-receipts/{$receipt->uuid}/approve",
                    )
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors([
                        'receipt',
                    ]);

                $receipt->refresh();

                expect(
                    $receipt->status,
                )->toBe(
                    PaymentReceiptStatus::Rejected,
                );

                expect(
                    $receipt->rejection_reason,
                )->toBe(
                    'El comprobante no es legible.',
                );
            },
        );

        it(
            'valida la paginación del listado pendiente',
            function (): void {
                /** @var \Tests\TestCase $this */
                $operator = paymentReceiptUser(
                    'operator',
                );

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->getJson(
                        '/api/v1/operator/payment-receipts?per_page=101',
                    )
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors([
                        'per_page',
                    ]);

                $this
                    ->actingAs(
                        $operator,
                        'sanctum',
                    )
                    ->getJson(
                        '/api/v1/operator/payment-receipts?per_page=0',
                    )
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors([
                        'per_page',
                    ]);
            },
        );
        it(
            'elimina archivos vencidos y conserva el registro histórico',
            function (): void {
                /** @var \Tests\TestCase $this */
                $customer = paymentReceiptUser(
                    'customer',
                );

                $order = paymentReceiptOrder(
                    $customer,
                );

                $path =
                    '2026/08/order-'.
                    $order->id.
                    '/expired-receipt.pdf';

                Storage::disk(
                    'payment_receipts',
                )->put(
                    $path,
                    'contenido-de-prueba',
                );

                $receipt = PaymentReceipt::query()
                    ->create([
                        'uuid' => (string) Str::uuid(),

                        'order_id' => (int) $order->id,

                        'user_id' => (int) $customer->id,

                        'disk' => 'payment_receipts',

                        'file_path' => $path,

                        'original_name' => 'expired-receipt.pdf',

                        'mime_type' => 'application/pdf',

                        'file_size' => 19,

                        'status' => PaymentReceiptStatus::Approved,

                        'submitted_at' => now()->subDays(100),

                        'reviewed_at' => now()->subDays(100),

                        'expires_at' => now()->subDay(),
                    ]);

                $this->assertTrue(
                    Storage::disk(
                        'payment_receipts',
                    )->exists(
                        $path,
                    ),
                    'El archivo debía existir antes de ejecutar la limpieza.',
                );

                $this
                    ->artisan(
                        'payment-receipts:prune',
                    )
                    ->expectsOutput(
                        'Archivos de comprobantes eliminados: 1',
                    )
                    ->assertSuccessful();

                $this->assertFalse(
                    Storage::disk(
                        'payment_receipts',
                    )->exists(
                        $path,
                    ),
                    'El archivo vencido debía eliminarse del almacenamiento.',
                );

                $receipt->refresh();

                expect(
                    $receipt->file_path,
                )->toBeNull();

                expect(
                    $receipt->file_deleted_at,
                )->not->toBeNull();

                $this
                    ->artisan(
                        'payment-receipts:prune',
                    )
                    ->expectsOutput(
                        'Archivos de comprobantes eliminados: 0',
                    )
                    ->assertSuccessful();

                $receipt->refresh();

                expect(
                    $receipt->file_path,
                )->toBeNull();

                expect(
                    $receipt->file_deleted_at,
                )->not->toBeNull();

                expect(
                    $receipt->status,
                )->toBe(
                    PaymentReceiptStatus::Approved,
                );
            },
        );
    },
);
