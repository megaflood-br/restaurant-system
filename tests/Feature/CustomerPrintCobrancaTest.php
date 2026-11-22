<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPrintCobrancaTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_profile_shows_print_cobranca_when_there_are_open_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->customer();
        $this->createOrder($customer, $admin, status: 'pending', total: 40, productName: 'Prato aberto');

        $this->actingAs($admin)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Imprimir cobrança', false)
            ->assertSee('Pendente de pagamento', false)
            ->assertSee('R$ 40,00');
    }

    public function test_print_cobranca_lists_open_orders_with_items_and_totals(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->customer();

        $open = $this->createOrder($customer, $admin, status: 'ready', total: 55, productName: 'Costela', quantity: 1, unitPrice: 50, deliveryFee: 5);
        $another = $this->createOrder($customer, $admin, status: 'preparing', total: 30, productName: 'Suco', quantity: 2, unitPrice: 15, deliveryFee: 0);
        $this->createOrder($customer, $admin, status: 'delivered', total: 99, productName: 'Já pago');
        $this->createOrder($customer, $admin, status: 'cancelled', total: 10, productName: 'Cancelado');

        $this->actingAs($admin)
            ->get(route('customers.print-cobranca', $customer))
            ->assertOk()
            ->assertSee('*** COBRANÇA ***', false)
            ->assertSee($customer->name)
            ->assertSee($open->order_number)
            ->assertSee($another->order_number)
            ->assertSee('Costela')
            ->assertSee('Suco')
            ->assertSee('Taxa de entrega')
            ->assertSee('TOTAL')
            ->assertSee('R$ 85,00')
            ->assertDontSee('Já pago')
            ->assertDontSee('Cancelado');
    }

    public function test_print_cobranca_redirects_when_customer_has_no_open_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->customer();
        $this->createOrder($customer, $admin, status: 'delivered', total: 20, productName: 'Fechado');

        $this->actingAs($admin)
            ->get(route('customers.print-cobranca', $customer))
            ->assertRedirect(route('customers.show', $customer));
    }

    public function test_customer_profile_hides_print_button_without_open_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->customer();
        $this->createOrder($customer, $admin, status: 'delivered', total: 20, productName: 'Fechado');

        $this->actingAs($admin)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertDontSee('Imprimir cobrança', false)
            ->assertSee('R$ 0,00');
    }

    private function customer(): Customer
    {
        return Customer::create([
            'name' => 'Cliente Cobrança',
            'phone' => '5511999111222',
            'is_active' => true,
        ]);
    }

    private function createOrder(
        Customer $customer,
        User $user,
        string $status,
        float $total,
        string $productName,
        int $quantity = 1,
        float $unitPrice = 40,
        float $deliveryFee = 0,
    ): Order {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Pratos',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => $productName,
            'price' => $unitPrice,
            'is_available' => true,
        ]);

        $order = Order::create([
            'order_number' => 'PED-COB-'.uniqid(),
            'type' => 'delivery',
            'status' => $status,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'delivery_fee' => $deliveryFee,
            'delivery_address' => 'Rua Cobrança, 10',
            'total' => $total,
            'user_id' => $user->id,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $quantity * $unitPrice,
        ]);

        return $order->fresh(['items']);
    }
}
