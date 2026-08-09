<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\OrderPrinterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::with('items.product', 'user', 'customer')
            ->latest()
            ->paginate(10);

        $products = Product::with('category')
            ->where('is_available', true)
            ->orderBy('name')
            ->get();

        $customers = Customer::where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedCustomer = $request->filled('customer_id')
            ? Customer::find($request->integer('customer_id'))
            : null;

        return view('orders.index', compact('orders', 'products', 'customers', 'selectedCustomer'));
    }

    public function create(Request $request): View
    {
        $products = Product::with('category')
            ->where('is_available', true)
            ->orderBy('name')
            ->get();

        $customers = Customer::where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedCustomer = $request->filled('customer_id')
            ? Customer::find($request->integer('customer_id'))
            : null;

        $comandaNumber = $request->filled('comanda')
            ? $request->integer('comanda')
            : null;

        return view('orders.create', compact('products', 'customers', 'selectedCustomer', 'comandaNumber'));
    }

    public function store(Request $request, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:dine_in,delivery,takeaway'],
            'comanda_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $order = DB::transaction(function () use ($validated, $request) {
            $customer = isset($validated['customer_id'])
                ? Customer::find($validated['customer_id'])
                : null;

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'customer_id' => $customer?->id,
                'type' => $validated['type'],
                'comanda_number' => $validated['comanda_number'] ?? null,
                'customer_name' => $customer?->name ?? ($validated['customer_name'] ?? null),
                'customer_phone' => $customer?->phone ?? ($validated['customer_phone'] ?? null),
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
                'user_id' => $request->user()->id,
            ]);

            $total = 0;

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $subtotal = $product->price * $item['quantity'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal' => $subtotal,
                    'notes' => $item['notes'] ?? null,
                ]);

                $total += $subtotal;
            }

            $order->update(['total' => $total]);

            return $order;
        });

        $inventory->deductForOrder($order->fresh(['items.product.ingredients']), $request->user()->id);

        $this->tryPrint($order);

        if (config('printing.auto_print_on_create') && config('printing.driver') === 'browser') {
            return redirect()->route('orders.print', ['order' => $order, 'autoprint' => 1])
                ->with('success', 'Pedido criado com sucesso.');
        }

        return redirect()->route('orders.show', $order)->with('success', 'Pedido criado com sucesso.');
    }

    private function tryPrint(Order $order): void
    {
        if (! config('printing.enabled') || config('printing.driver') !== 'network') {
            return;
        }

        try {
            app(OrderPrinterService::class)->printOrder($order, 'kitchen');
        } catch (\Throwable) {
            // Impressão em rede é best-effort.
        }
    }

    public function show(Order $order): View
    {
        $order->load('items.product', 'user', 'customer', 'deliveryArea');

        return view('orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,preparing,ready,served,delivered,cancelled'],
        ]);

        $previousStatus = $order->status;
        $order->update(['status' => $validated['status']]);

        if ($validated['status'] === 'cancelled' && $previousStatus !== 'cancelled') {
            $inventory->restoreForOrder($order->fresh(['items.product.ingredients']), $request->user()->id);
        }

        return back()->with('success', 'Status do pedido atualizado.');
    }
}
