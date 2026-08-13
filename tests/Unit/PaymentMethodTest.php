<?php

namespace Tests\Unit;

use App\Support\PaymentMethod;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    public function test_detect_finds_pix_inside_sentence(): void
    {
        $this->assertSame('pix', PaymentMethod::detect('pode ser no pix'));
        $this->assertSame('pix', PaymentMethod::detect('Pix'));
        $this->assertSame('cash', PaymentMethod::detect('vou pagar em dinheiro'));
        $this->assertSame('credit', PaymentMethod::detect('cartão de crédito'));
        $this->assertNull(PaymentMethod::detect('quero strogonoff'));
    }
}
