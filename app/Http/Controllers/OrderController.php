<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\OrderPrinterService;
use App\Support\ProductSellable;
use App\Support\ProductVariants;
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

        $with = ['category', 'recipe'];
        if (ProductVariants::enabled()) {
            $with['variants'] = fn ($q) => $q->where('is_available', true)->orderBy('sort_order');
        }

        $products = Product::with($with)
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
        $with = ['category', 'recipe'];
        if (ProductVariants::enabled()) {
            $with['variants'] = fn ($q) => $q->where('is_available', true)->orderBy('sort_order');
        }

        $products = Product::with($with)
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
            'items.*.variant_id' => ProductVariants::variantIdRules(),
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
                $product = ProductVariants::loadProduct((int) $item['product_id']);
                $line = ProductSellable::orderItemAttributes(
                    $product,
                    (int) $item['quantity'],
                    isset($item['variant_id']) ? (int) $item['variant_id'] : null,
                    $item['notes'] ?? null,
                );

                $order->items()->create($line);
                $total += $line['subtotal'];
            }

            $order->update(['total' => $total]);

            return $order;
        });

        $inventory->deductForOrder($order->fresh(['items.product.recipe', 'items.productVariant.recipe']), $request->user()->id);

        $this->tryPrint($order);

        if (config('printing.auto_print_on_create') && config('printing.driver') === 'browser') {
            return redirect()->route('orders.print', ['order' => $order, 'autoprint' => 1])
                ->with('success', 'Pedido criado com sucesso.');
        }

        return redirect()->route('orders.show', $order)->with('success', 'Pedido criado com sucesso.');
    }

    private function tryPrint(Order $order): void
    {
        try {
            app(OrderPrinterService::class)->dispatchKitchenPrint($order);
        } catch (\Throwable) {
            // Impressão em rede/agente é best-effort.
        }
    }

    public function show(Order $order): View
    {
        $order->load('items.product', 'user', 'customer', 'deliveryArea');

        return view('orders.show', compact('order'));
    }

    public function destroy(Request $request, Order $order, InventoryService $inventory): RedirectResponse
    {
        $orderNumber = $order->order_number;

        DB::transaction(function () use ($order, $inventory, $request) {
            $fresh = $order->fresh(['items.product.recipe', 'items.productVariant.recipe']);

            if ($fresh && $fresh->inventory_deducted_at && $fresh->status !== 'cancelled') {
                $inventory->restoreForOrder($fresh, $request->user()->id);
            }

            $order->delete();
        });

        return redirect()
            ->route('orders.index')
            ->with('success', "Pedido {$orderNumber} excluído com sucesso.");
    }

    public function updateStatus(Request $request, Order $order, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,preparing,ready,served,delivered,cancelled'],
        ]);

        $previousStatus = $order->status;
        $order->update(['status' => $validated['status']]);

        if ($validated['status'] === 'cancelled' && $previousStatus !== 'cancelled') {
            $inventory->restoreForOrder($order->fresh(['items.product.recipe', 'items.productVariant.recipe']), $request->user()->id);
        }

        return back()->with('success', 'Status do pedido atualizado.');
    }
}
