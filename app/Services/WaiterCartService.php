<?php

namespace App\Services;

class WaiterCartService extends CartService
{
    public function __construct()
    {
        parent::__construct('waiter_cart');
    }
}
