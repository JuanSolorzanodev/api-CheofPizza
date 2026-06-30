<?php

namespace App\DTOs\Auth;

use App\Models\Cart;
use App\Models\User;

final readonly class LoginResponse
{
    public function __construct(
        public string $token,
        public User $user,
        public Cart $cart,
        public string $cartSession,
    ) {}
}
