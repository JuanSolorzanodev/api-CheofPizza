<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('operator.orders', function (User $user) {
    return in_array($user->role?->role_name, ['operator', 'admin'], true);
}, ['guards' => ['sanctum']]);

Broadcast::channel('operator.orders.{orderId}', function (User $user, int $orderId) {
    if (!in_array($user->role?->role_name, ['operator', 'admin'], true)) {
        return false;
    }

    return Order::query()->whereKey($orderId)->exists();
}, ['guards' => ['sanctum']]);

Broadcast::channel('customer.orders.{userId}', function (User $user, int $userId) {
    return $user->role?->role_name === 'customer'
        && (int) $user->id === (int) $userId;
}, ['guards' => ['sanctum']]);

Broadcast::channel('customer.order.{orderId}', function (User $user, int $orderId) {
    return $user->role?->role_name === 'customer'
        && Order::query()
            ->whereKey($orderId)
            ->where('user_id', $user->id)
            ->exists();
}, ['guards' => ['sanctum']]);
