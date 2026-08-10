<?php

namespace App\Http\Controllers\Waiter;

use App\Http\Controllers\Controller;
use App\Support\MenuCatalog;
use App\Models\Order;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\OrderPrinterService;
use App\Services\WaiterCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WaiterOrderController extends Controller
{
    public function menu(Request $request, WaiterCartService $cart): View
    {
        if ($request->filled('comanda')) {
            $cart->setComandaNumber((int) $request->query('comanda'));
        }

        $categories = MenuCatalog::categories();

        return view('waiter.menu', [
            'categories' => $categories,
            'comandaNumber' => $cart->all()['comanda_number'],
        ]);
    }

    public function cartSummary(WaiterCartService $cart): JsonResponse
    {
        return response()->json([
            'count' => $cart->count(),
            'total' => $cart->total(),
            'comanda_number' => $cart->all()['comanda_number'],
        ]);
    }

    public function setComanda(Request $request, WaiterCartService $cart): RedirectResponse
    {
        $validated = $request->validate([
            'comanda_number' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $cart->setComandaNumber((int) $validated['comanda_number']);

        return back()->with('success', 'Comanda '.$validated['comanda_number'].' selecionada.');
    }

    public function cart(WaiterCartService $cart): View|RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect()->route('waiter.menu')->with('info', 'Nenhum item no pedido.');
        }

        return view('waiter.cart', [
            'items' => $cart->items(),
            'total' => $cart->total(),
            'comandaNumber' => $cart->all()['comanda_number'],
            'categories' => MenuCatalog::categories(),
        ]);
    }

    public function add(Request $request, WaiterCartService $cart): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'variant_id' => \App\Support\ProductVariants::variantIdRules(),
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:255'],
            'comanda_number' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        if ($request->filled('comanda_number')) {
            $cart->setComandaNumber((int) $validated['comanda_number']);
        }

        $product = \App\Support\ProductVariants::loadProduct((int) $validated['product_id']);
        \App\Support\ProductSellable::resolve($product, $validated['variant_id'] ?? null);

        $cart->add(
            (int) $validated['product_id'],
            (int) $validated['quantity'],
            $validated['notes'] ?? null,
            isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Item adicionado.',
                'cart_count' => $cart->count(),
                'cart_total' => $cart->total(),
            ]);
        }

        return back()->with('success', 'Item adicionado.');
    }

    public function update(Request $request, WaiterCartService $cart): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
            'notes' => ['nullable', 'string'],
        ]);

        $cart->update(
            (int) $validated['product_id'],
            (int) $validated['quantity'],
            $validated['notes'] ?? null,
            isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
        );

        return back();
    }

    public function remove(Request $request, WaiterCartService $cart): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ]);

        $cart->remove(
            (int) $validated['product_id'],
            $validated['notes'] ?? null,
            isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
        );

        return back()->with('success', 'Item removido.');
    }

    public function store(Request $request, WaiterCartService $cart, OrderPrinterService $printer, InventoryService $inventory): RedirectResponse|JsonResponse
    {
        if ($cart->isEmpty()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Adicione itens antes de enviar.'], 422);
            }

            return redirect()->route('waiter.menu')->with('info', 'Adicione itens antes de enviar.');
        }

        $validated = $request->validate([
            'comanda_number' => ['required', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:500'],
            'return_url' => ['nullable', 'string', 'max:500'],
        ]);

        $cart->setComandaNumber((int) $validated['comanda_number']);

        $order = DB::transaction(function () use ($cart, $validated, $request) {
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'type' => 'dine_in',
                'comanda_number' => (int) $validated['comanda_number'],
                'customer_id' => session('comanda_customer_id'),
                'customer_name' => session('comanda_customer_name'),
                'notes' => filled($validated['notes'] ?? null) ? $validated['notes'] : null,
                'status' => 'pending',
                'user_id' => $request->user()->id,
            ]);

            $total = 0;

            foreach ($cart->items() as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['variant_id'] ?? null,
                    'variant_label' => $item['variant_label'] ?? null,
                    'product_name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'subtotal' => $item['subtotal'],
                    'notes' => $item['notes'],
                ]);

                $total += $item['subtotal'];
            }

            $order->update(['total' => $total]);

            return $order;
        });

        $inventory->deductForOrder($order->fresh(['items.product.recipe', 'items.productVariant.recipe']), $request->user()->id);

        $comandaNumber = (int) $validated['comanda_number'];
        $cart->clearItems();
        $cart->setComandaNumber($comandaNumber);

        if (config('printing.enabled') && config('printing.driver') === 'network') {
            try {
                $printer->printOrder($order->fresh(['items.product', 'user']), 'kitchen');
            } catch (\Throwable) {
                //
            }
        }

        session([
            'waiter_last_order_id' => $order->id,
            'order_return_url' => $this->safeReturnUrl($validated['return_url'] ?? null),
        ]);

        $returnUrl = session('order_return_url');

        if ($request->wantsJson()) {
            if (config('printing.enabled') && config('printing.driver') === 'browser') {
                if ($returnUrl) {
                    return response()->json([
                        'message' => 'Pedido enviado.',
                        'print_url' => route('waiter.autoprint', [
                            'order' => $order,
                            'return' => $returnUrl,
                        ]),
                        'reload' => true,
                    ]);
                }

                return response()->json([
                    'message' => 'Pedido enviado.',
                    'redirect' => route('waiter.autoprint', $order),
                ]);
            }

            return response()->json([
                'message' => 'Pedido enviado.',
                'reload' => true,
            ]);
        }

        if (config('printing.enabled') && config('printing.driver') === 'browser') {
            if ($returnUrl) {
                return redirect()->route('waiter.autoprint', [
                    'order' => $order,
                    'return' => $returnUrl,
                ]);
            }

            return redirect()->route('waiter.autoprint', $order);
        }

        if ($returnUrl) {
            return redirect($returnUrl)->with('success', 'Pedido enviado.');
        }

        return redirect()->route('waiter.success');
    }

    public function autoprint(Request $request, Order $order): View|RedirectResponse
    {
        $returnUrl = $this->safeReturnUrl($request->query('return'))
            ?? session('order_return_url');

        if (session('waiter_last_order_id') !== $order->id && ! $returnUrl) {
            return redirect()->route('waiter.menu');
        }

        $order->load('items.product', 'user');

        return view('waiter.autoprint', [
            'order' => $order,
            'returnUrl' => $returnUrl,
        ]);
    }

    private function safeReturnUrl(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        $url = trim($url);

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $parsed = parse_url($url);

        if ($parsed === false || empty($parsed['host'])) {
            return null;
        }

        $allowedHosts = array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            parse_url((string) url('/'), PHP_URL_HOST),
            'localhost',
            '127.0.0.1',
        ]);

        if (! in_array($parsed['host'], $allowedHosts, true)) {
            return null;
        }

        $path = $parsed['path'] ?? '/';
        $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';

        return $path.$query;
    }

    public function success(): View|RedirectResponse
    {
        $orderId = session('waiter_last_order_id');

        if (! $orderId) {
            return redirect()->route('waiter.menu');
        }

        $order = Order::with('items.product')->findOrFail($orderId);
        session()->forget('waiter_last_order_id');

        return view('waiter.success', compact('order'));
    }

    public function orders(): View
    {
        $orders = Order::with('items')
            ->where('type', 'dine_in')
            ->whereIn('status', ['pending', 'preparing', 'ready', 'served'])
            ->whereDate('created_at', today())
            ->oldest()
            ->limit(50)
            ->get();

        return view('waiter.orders', compact('orders'));
    }

    public function markServed(Order $order): RedirectResponse
    {
        if (! $order->canBeMarkedServed()) {
            return back()->with('error', 'Este pedido não pode ser marcado como entregue.');
        }

        $order->update(['status' => 'served']);

        return back()->with('success', 'Pedido '.$order->order_number.' marcado como entregue.');
    }
}
