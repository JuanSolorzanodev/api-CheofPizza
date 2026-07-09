/*
$activeStatusId = $this->activeStatusIdOrFail();

    if ($userId) {

        $userCart = Cart::where('user_id', $userId)
            ->where('cart_status_id', $activeStatusId)
            ->latest('id')
            ->first();

        if (!$userCart) {

            $userCart = Cart::create([
                'user_id' => $userId,
                'cart_status_id' => $activeStatusId,
                'session_id' => $cartSession,
                'total' => 0,
            ]);

        } elseif (!$userCart->session_id) {

            $userCart->session_id = $cartSession;
            $userCart->save();

        }

        $guestCart = Cart::whereNull('user_id')
            ->where('session_id', $cartSession)
            ->where('cart_status_id', $activeStatusId)
            ->latest('id')
            ->first();

        if ($guestCart && $guestCart->id !== $userCart->id) {
            $this->mergeGuestCartIntoUserCart(
                $guestCart,
                $userCart
            );
        }

        return $this->loadCart($userCart);
    }

    $cart = Cart::whereNull('user_id')
        ->where('session_id', $cartSession)
        ->where('cart_status_id', $activeStatusId)
        ->latest('id')
        ->first();

    if (!$cart) {

        $cart = Cart::create([
            'user_id' => null,
            'cart_status_id' => $activeStatusId,
            'session_id' => $cartSession,
            'total' => 0,
        ]);

    }

    return $this->loadCart($cart);

*/
