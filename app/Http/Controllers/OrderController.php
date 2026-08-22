<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesBulkDestroy;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\CashFlowService;
use App\Services\DeliveryFeeService;
use App\Services\InventoryService;
use App\Services\OrderPrinterService;
use App\Support\PaymentMethod;
use App\Support\ProductSellable;
use App\Support\ProductVariants;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    use HandlesBulkDestroy;

    public function index(Request $request): View
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date'))->timezone(config('app.timezone'))
            : today();

        $orders = Order::with('items.product', 'user', 'customer')
            ->whereDate('created_at', $date)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $dayQuery = Order::query()->whereDate('created_at', $date);
        $dailyStats = [
            'orders_count' => (clone $dayQuery)->count(),
            'revenue' => (float) (clone $dayQuery)->where('status', '!=', 'cancelled')->sum('total'),
            'cancelled_count' => (clone $dayQuery)->where('status', 'cancelled')->count(),
        ];

        $with = ['category', 'recipe'];
        if (ProductVariants::enabled()) {
            $with['variants'] = fn ($q) => $q->where('is_available', true)->orderBy('sort_order');
        }

        $products = Product::with($with)
            ->where('is_available', true)
            ->orderBy('name')
            ->get();

        $selectedCustomer = $request->filled('customer_id')
            ? Customer::find($request->integer('customer_id'))
            : null;

        return view('orders.index', [
            'orders' => $orders,
            'products' => $products,
            'selectedCustomer' => $selectedCustomer,
            'date' => $date->toDateString(),
            'dailyStats' => $dailyStats,
        ]);
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

        $selectedCustomer = $request->filled('customer_id')
            ? Customer::find($request->integer('customer_id'))
            : null;

        $comandaNumber = $request->filled('comanda')
            ? $request->integer('comanda')
            : null;

        return view('orders.create', compact('products', 'selectedCustomer', 'comandaNumber'));
    }

    public function deliveryQuote(Customer $customer, DeliveryFeeService $deliveryFee): JsonResponse
    {
        $address = $customer->resolvedDeliveryAddress();

        if ($address === null) {
            return response()->json([
                'ok' => false,
                'reason' => 'missing_address',
                'message' => 'Cliente sem endereço cadastrado.',
            ]);
        }

        $diagnosed = $deliveryFee->diagnoseAddress($address);

        if ($diagnosed['quote'] === null) {
            $messages = [
                'missing_origin' => 'Origem de entrega não configurada nas configurações gerais.',
                'geocode_failed' => 'Não foi possível localizar o endereço do cliente.',
                'out_of_range' => 'Endereço fora das áreas de entrega cadastradas.',
            ];

            return response()->json([
                'ok' => false,
                'reason' => $diagnosed['reason'],
                'distance_km' => $diagnosed['distance_km'],
                'delivery_address' => $address,
                'message' => $messages[$diagnosed['reason'] ?? ''] ?? 'Não foi possível calcular a taxa de entrega.',
            ]);
        }

        $quote = $diagnosed['quote'];

        return response()->json([
            'ok' => true,
            'delivery_address' => $address,
            'distance_km' => $quote['distance_km'],
            'delivery_area_id' => $quote['delivery_area_id'],
            'delivery_area_name' => $quote['delivery_area_name'],
            'delivery_fee' => $quote['delivery_fee'],
        ]);
    }

    public function store(Request $request, InventoryService $inventory, DeliveryFeeService $deliveryFee): RedirectResponse
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
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:'.implode(',', PaymentMethod::keys())],
        ]);

        $customer = isset($validated['customer_id'])
            ? Customer::find($validated['customer_id'])
            : null;

        // Cotação (HTTP/Nominatim) FORA da transaction — no SQLite, HTTP lento
        // dentro de DB::transaction() segura o lock e causa "database is locked".
        $deliveryFeeAmount = 0.0;
        $deliveryAreaId = null;
        $deliveryAddress = null;
        $discountAmount = (float) ($validated['discount'] ?? 0);

        if ($validated['type'] === 'delivery') {
            if ($customer) {
                $deliveryAddress = $customer->resolvedDeliveryAddress();
            }

            if ($request->filled('delivery_fee')) {
                $deliveryFeeAmount = (float) $validated['delivery_fee'];
            } elseif ($deliveryAddress !== null) {
                $quote = $deliveryFee->quoteForAddress($deliveryAddress);

                if ($quote) {
                    $deliveryFeeAmount = (float) $quote['delivery_fee'];
                    $deliveryAreaId = $quote['delivery_area_id'];
                }
            }
        }

        $order = DB::transaction(function () use (
            $validated,
            $request,
            $customer,
            $deliveryFeeAmount,
            $deliveryAreaId,
            $deliveryAddress,
            $discountAmount,
        ) {
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'customer_id' => $customer?->id,
                'type' => $validated['type'],
                'comanda_number' => $validated['comanda_number'] ?? null,
                'customer_name' => $customer?->name ?? ($validated['customer_name'] ?? null),
                'customer_phone' => $customer?->phone ?? ($validated['customer_phone'] ?? null),
                'delivery_area_id' => $deliveryAreaId,
                'delivery_fee' => $deliveryFeeAmount,
                'discount' => $discountAmount,
                'delivery_address' => $deliveryAddress,
                'notes' => $validated['notes'] ?? null,
                'payment_method' => ($validated['payment_method'] ?? null) ?: null,
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

            $order->update([
                'total' => max(0, $total + $deliveryFeeAmount - $discountAmount),
            ]);

            return $order;
        });

        $inventory->deductForOrder($order->fresh(['items.product.recipe', 'items.productVariant.recipe']), $request->user()->id);

        $this->tryPrintOnCreate($order);

        if (config('printing.auto_print_on_create') && config('printing.driver') === 'browser') {
            return redirect()->route('orders.print', ['order' => $order, 'autoprint' => 1])
                ->with('success', 'Pedido criado com sucesso.');
        }

        return redirect()->route('orders.show', $order)->with('success', 'Pedido criado com sucesso.');
    }

    private function tryPrintOnCreate(Order $order): void
    {
        try {
            app(OrderPrinterService::class)->maybePrintOnCreate($order);
        } catch (\Throwable) {
            // Impressão em rede/agente é best-effort.
        }
    }

    public function show(Request $request, Order $order): View
    {
        $order->load('items.product', 'user', 'customer', 'deliveryArea');

        return view('orders.show', [
            'order' => $order,
            'editing' => $request->boolean('edit'),
            'customers' => Customer::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'phone']),
        ]);
    }

    public function updateDetails(Request $request, Order $order): RedirectResponse
    {
        if ($redirect = $this->rejectIfNotEditable($order)) {
            return $redirect;
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:'.implode(',', PaymentMethod::keys())],
        ]);

        $customer = isset($validated['customer_id'])
            ? Customer::query()->find($validated['customer_id'])
            : null;

        $order->update([
            'notes' => $validated['notes'] ?? null,
            'customer_id' => $customer?->id,
            'customer_name' => $customer?->name ?? ($validated['customer_name'] ?? $order->customer_name),
            'customer_phone' => $customer?->phone ?? ($validated['customer_phone'] ?? $order->customer_phone),
            'delivery_address' => $order->type === 'delivery'
                ? ($validated['delivery_address'] ?? $order->delivery_address)
                : $order->delivery_address,
            'delivery_fee' => $order->type === 'delivery' && array_key_exists('delivery_fee', $validated)
                ? (float) ($validated['delivery_fee'] ?? 0)
                : $order->delivery_fee,
            'discount' => array_key_exists('discount', $validated)
                ? (float) ($validated['discount'] ?? 0)
                : $order->discount,
            'payment_method' => array_key_exists('payment_method', $validated)
                ? ($validated['payment_method'] ?: null)
                : $order->payment_method,
        ]);

        $order->recalculateTotal();

        return redirect()
            ->route('orders.show', ['order' => $order, 'edit' => 1])
            ->with('success', 'Dados do pedido atualizados.');
    }

    public function updateItem(
        Request $request,
        Order $order,
        OrderItem $item,
        InventoryService $inventory,
    ): RedirectResponse {
        if ($redirect = $this->rejectIfNotEditable($order)) {
            return $redirect;
        }

        if ((int) $item->order_id !== (int) $order->id) {
            return back()->with('error', 'Item não pertence a este pedido.');
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($item, $validated, $order) {
            $item->update([
                'quantity' => $validated['quantity'],
                'subtotal' => round((float) $item->unit_price * (int) $validated['quantity'], 2),
                'notes' => $validated['notes'] ?? null,
            ]);

            $order->recalculateTotal();
        });

        $inventory->resyncForOrder($order->fresh(), $request->user()->id);

        return redirect()
            ->route('orders.show', ['order' => $order, 'edit' => 1])
            ->with('success', 'Item atualizado.');
    }

    public function destroyItem(
        Request $request,
        Order $order,
        OrderItem $item,
        InventoryService $inventory,
    ): RedirectResponse {
        if ($redirect = $this->rejectIfNotEditable($order)) {
            return $redirect;
        }

        if ((int) $item->order_id !== (int) $order->id) {
            return back()->with('error', 'Item não pertence a este pedido.');
        }

        DB::transaction(function () use ($item, $order, $inventory, $request) {
            $fresh = $order->fresh(['items.product.recipe.ingredients', 'items.productVariant.recipe.ingredients']);
            $hadInventory = (bool) $fresh?->inventory_deducted_at;

            if ($hadInventory && $fresh) {
                $inventory->restoreForOrder($fresh, $request->user()->id);
            }

            $item->delete();
            $order->refresh()->load('items');

            if ($order->items->isEmpty()) {
                $order->update([
                    'status' => 'cancelled',
                    'total' => max(0, (float) $order->delivery_fee - (float) $order->discount),
                ]);

                return;
            }

            $order->recalculateTotal();

            if ($hadInventory) {
                $inventory->deductForOrder(
                    $order->fresh(['items.product.recipe.ingredients', 'items.productVariant.recipe.ingredients']),
                    $request->user()->id
                );
            }
        });

        return redirect()
            ->route('orders.show', ['order' => $order, 'edit' => 1])
            ->with('success', 'Item removido.');
    }

    private function rejectIfNotEditable(Order $order): ?RedirectResponse
    {
        if ($order->status === 'cancelled') {
            return redirect()
                ->route('orders.show', $order)
                ->with('error', 'Pedido cancelado não pode ser editado.');
        }

        return null;
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

    public function bulkDestroy(Request $request, InventoryService $inventory): RedirectResponse
    {
        $ids = $this->bulkIds($request);
        $deleted = 0;

        foreach (Order::query()->whereIn('id', $ids)->get() as $order) {
            DB::transaction(function () use ($order, $inventory, $request) {
                $fresh = $order->fresh(['items.product.recipe', 'items.productVariant.recipe']);

                if ($fresh && $fresh->inventory_deducted_at && $fresh->status !== 'cancelled') {
                    $inventory->restoreForOrder($fresh, $request->user()->id);
                }

                $order->delete();
            });

            $deleted++;
        }

        return $this->bulkResultRedirect('orders.index', $deleted, 0, 'pedido', 'pedidos');
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

        if ($validated['status'] === 'delivered' && $previousStatus !== 'delivered') {
            try {
                app(CashFlowService::class)->recordOrderSale(
                    $order->fresh(),
                    $request->user()->id
                );
            } catch (\Throwable) {
                // best-effort
            }
        }

        try {
            app(OrderPrinterService::class)->maybePrintOnStatusChange(
                $order->fresh(['items.product', 'customer', 'deliveryArea', 'user']),
                $previousStatus,
                $validated['status']
            );
        } catch (\Throwable) {
            // best-effort
        }

        $message = 'Status do pedido atualizado.';
        if ($validated['status'] === 'preparing' && $previousStatus !== 'preparing' && config('printing.print_on_preparing')) {
            $message = config('printing.driver') === 'agent'
                ? 'Status atualizado para Preparando. Comanda enfileirada para impressão.'
                : 'Status atualizado para Preparando. Comanda enviada para impressão.';
        }

        return back()->with('success', $message);
    }
}
