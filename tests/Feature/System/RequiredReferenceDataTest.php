<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Services\Cart\CartService;
use Illuminate\Support\Facades\DB;

it('creates all required system reference data through migrations', function (): void {
    expect(
        DB::table('roles')
            ->orderBy('role_name')
            ->pluck('role_name')
            ->all(),
    )->toBe([
        'admin',
        'customer',
        'operator',
    ]);

    expect(
        DB::table('cart_statuses')
            ->orderBy('status_name')
            ->pluck('status_name')
            ->all(),
    )->toBe([
        'abandoned',
        'active',
        'ordered',
    ]);

    expect(
        DB::table('order_statuses')
            ->orderBy('status_name')
            ->pluck('status_name')
            ->all(),
    )->toBe([
        'cancelled',
        'confirmed',
        'delivered',
        'on_the_way',
        'pending',
        'preparing',
        'ready',
    ]);

    expect(
        DB::table('delivery_types')
            ->orderBy('delivery_type_name')
            ->pluck('delivery_type_name')
            ->all(),
    )->toBe([
        'delivery',
        'pickup',
    ]);

    expect(
        DB::table('payment_methods')
            ->orderBy('name')
            ->pluck('name')
            ->all(),
    )->toBe([
        'card',
        'cash',
        'transfer',
    ]);

    expect(
        DB::table('personalization_actions')
            ->orderBy('action_name')
            ->pluck('action_name')
            ->all(),
    )->toBe([
        'Agregar',
        'Extra',
        'Quitar',
    ]);
});

it('can create an active guest cart without running seeders', function (): void {
    /** @var CartService $cartService */
    $cartService = app(CartService::class);

    $cart = $cartService->getOrCreateActiveCart(
        userId: null,
        sessionId: 'required-reference-data-test-session',
    );

    expect($cart)
        ->toBeInstanceOf(Cart::class)
        ->and($cart->user_id)
        ->toBeNull()
        ->and($cart->session_id)
        ->toBe('required-reference-data-test-session')
        ->and((float) $cart->total)
        ->toBe(0.0)
        ->and($cart->cartStatus?->status_name)
        ->toBe('active');

expect(
    DB::table('carts')
        ->where('id', $cart->id)
        ->whereNull('user_id')
        ->where(
            'session_id',
            'required-reference-data-test-session',
        )
        ->exists(),
)->toBeTrue();
});

it('does not create optional commercial catalog data through migrations', function (): void {
    expect(DB::table('pizzas')->count())
        ->toBe(0)
        ->and(DB::table('promotions')->count())
        ->toBe(0)
        ->and(DB::table('bank_accounts')->count())
        ->toBe(0)
        ->and(DB::table('whats_app_settings')->count())
        ->toBe(0);
});
