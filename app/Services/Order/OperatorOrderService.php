<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderStatusChange;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Events\Operator\OrderStatusChanged;

class OperatorOrderService
{
    private const FINAL_STATUSES = ['delivered', 'cancelled'];

    private const TRANSITIONS = [
        'pending'    => ['confirmed', 'cancelled'],
        'confirmed'  => ['preparing', 'cancelled'],
        'preparing'  => ['ready', 'cancelled'],
        'ready'      => ['on_the_way', 'delivered', 'cancelled'], // delivered solo si pickup
        'on_the_way' => ['delivered', 'cancelled'],
        'delivered'  => [],
        'cancelled'  => [],
    ];

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int)($filters['per_page'] ?? 15);

        $q = Order::query()
            ->with([
                'user',
                'deliveryType',
                'paymentMethod',
                'orderStatus',
            ])
            ->latest('ordered_at');

        $this->applyFilters($q, $filters);

        return $q->paginate($perPage);
    }

    /**
     * Detalle operativo (1 query principal + eager loads) sin N+1
     */
    public function findOrFail(int $orderId): Order
    {
        return Order::query()
            ->with([
                // -------------------------
                // Cabecera
                // -------------------------
                'orderStatus:id,status_name',
                'deliveryType:id,delivery_type_name',
                'paymentMethod:id,name',
                'user:id,first_name,last_name,email,phone',

                // -------------------------
                // Items + relaciones necesarias para cocina
                // -------------------------
                'orderItems' => function ($q) {
                    // Importante: NO hacer select agresivo que rompa FKs.
                    // Cargamos todo lo necesario para resource sin inventar columnas.
                    $q->select([
                        'id',
                        'order_id',
                        'promotion_id',
                        'promotion_name',
                        'pizza_id',
                        'pizza_name',
                        'pizza_id_second',
                        'pizza_name_second',
                        'size_id',
                        'size_name',
                        'category_name',
                        'category_name_second',
                        'is_half_and_half',
                        'quantity',
                        'unit_price',
                        'subtotal',
                    ]);
                },

                // Pizza normal (preferida: belongsToMany ingredients)
                'orderItems.pizza:id,pizza_name,description',
                'orderItems.pizza.ingredients:id,ingredient_name',

                // Fallback (si en algún lado estás usando pizzaIngredients)
                'orderItems.pizza.pizzaIngredients:id,pizza_id,ingredient_id',
                'orderItems.pizza.pizzaIngredients.ingredient:id,ingredient_name',

                // Mitad y mitad
                'orderItems.pizzaSecond:id,pizza_name,description',
                'orderItems.pizzaSecond.ingredients:id,ingredient_name',
                'orderItems.pizzaSecond.pizzaIngredients:id,pizza_id,ingredient_id',
                'orderItems.pizzaSecond.pizzaIngredients.ingredient:id,ingredient_name',

                // Promo
                'orderItems.orderPromotionItems:id,order_item_id,pizza_id,pizza_name',
                'orderItems.orderPromotionItems.pizza:id,pizza_name,description',
                'orderItems.orderPromotionItems.pizza.ingredients:id,ingredient_name',
                'orderItems.orderPromotionItems.pizza.pizzaIngredients:id,pizza_id,ingredient_id',
                'orderItems.orderPromotionItems.pizza.pizzaIngredients.ingredient:id,ingredient_name',

                // Personalizaciones (tu resource las usa)
                'orderItems.orderItemPersonalizations:id,order_item_id,ingredient_id,ingredient_name,personalization_action_id,applies_to,modification_type,extra_price',
                'orderItems.orderItemPersonalizations.personalizationAction:id,action_name',

                // Historial de estados
                'statusChanges.fromStatus:id,status_name',
                'statusChanges.toStatus:id,status_name',
                'statusChanges.changedBy:id,first_name,last_name,email',
            ])
            ->findOrFail($orderId);
    }

    public function changeStatus(
        int $orderId,
        string $toStatusName,
        ?string $note,
        int $changedByUserId
    ): Order {
        return DB::transaction(function () use ($orderId, $toStatusName, $note, $changedByUserId) {

            /** @var Order $order */
            $order = Order::query()
                ->with(['deliveryType', 'orderStatus'])
                ->lockForUpdate()
                ->findOrFail($orderId);

            $fromName = (string)($order->orderStatus?->status_name ?? '');

            if ($fromName === '') {
                throw ValidationException::withMessages([
                    'status' => ['La orden no tiene estado actual válido.'],
                ]);
            }

            if ($fromName === $toStatusName) {
                throw ValidationException::withMessages([
                    'to_status' => ['La orden ya se encuentra en ese estado.'],
                ]);
            }

            if (in_array($fromName, self::FINAL_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'to_status' => ["No puedes cambiar una orden en estado final ({$fromName})."],
                ]);
            }

            $allowed = self::TRANSITIONS[$fromName] ?? [];
            if (!in_array($toStatusName, $allowed, true)) {
                throw ValidationException::withMessages([
                    'to_status' => ["Transición no permitida: {$fromName} → {$toStatusName}."],
                ]);
            }

            // Regla: delivered directo desde ready SOLO si pickup
            $deliveryType = (string)($order->deliveryType?->delivery_type_name ?? '');
            if ($fromName === 'ready' && $toStatusName === 'delivered' && $deliveryType === 'delivery') {
                throw ValidationException::withMessages([
                    'to_status' => ['Para delivery debes pasar por on_the_way antes de delivered.'],
                ]);
            }

            $toStatus = OrderStatus::query()->firstWhere('status_name', $toStatusName);
            if (!$toStatus) {
                throw ValidationException::withMessages([
                    'to_status' => ['El estado destino no existe en order_statuses. Ejecuta CommerceSeeder.'],
                ]);
            }

            $fromStatusId = (int)$order->order_status_id;
            $toStatusId   = (int)$toStatus->id;

            $order->forceFill([
                'order_status_id' => $toStatusId,
            ])->save();

            OrderStatusChange::query()->create([
                'order_id' => (int)$order->id,
                'from_order_status_id' => $fromStatusId,
                'to_order_status_id' => $toStatusId,
                'changed_by_user_id' => $changedByUserId,
                'changed_at' => now(),
                'note' => $note,
            ]);

            $freshOrder = $this->findOrFail((int) $order->id);

                event(new OrderStatusChanged(
                    $freshOrder,
                    $fromName,
                    $toStatusName
                ));

                return $freshOrder;
        });
    }

    public function queueCounts(): array
    {
        $rows = OrderStatus::query()
            ->select(['id', 'status_name'])
            ->withCount(['orders as orders_count'])
            ->orderBy('id')
            ->get();

        $counts = [];
        foreach ($rows as $r) {
            $counts[$r->status_name] = (int)$r->orders_count;
        }

        return $counts;
    }

    public function allStatuses(): array
    {
        return OrderStatus::query()
            ->orderBy('id')
            ->pluck('status_name')
            ->values()
            ->all();
    }

    private function applyFilters(Builder $q, array $filters): void
    {
        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where(function (Builder $w) use ($term) {
                $w->where('order_number', 'like', "%{$term}%")
                    ->orWhere('address', 'like', "%{$term}%");
            });
        }

        if (!empty($filters['status'])) {
            $status = (string)$filters['status'];
            $q->whereHas('orderStatus', fn (Builder $s) => $s->where('status_name', $status));
        }

        if (!empty($filters['delivery_type'])) {
            $deliveryType = (string)$filters['delivery_type'];
            $q->whereHas('deliveryType', fn (Builder $d) => $d->where('delivery_type_name', $deliveryType));
        }

        if (!empty($filters['payment_method'])) {
            $pm = (string)$filters['payment_method'];
            $q->whereHas('paymentMethod', fn (Builder $p) => $p->where('name', $pm));
        }

        if (!empty($filters['date_from'])) {
            $q->whereDate('ordered_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $q->whereDate('ordered_at', '<=', $filters['date_to']);
        }
    }
}
