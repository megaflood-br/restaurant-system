<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ComandaBillService;
use App\Services\InventoryService;
use App\Services\WaiterCartService;
use App\Services\OrderPrinterService;
use App\Support\ComandaCustomer;
use App\Support\MenuCatalog;
use App\Support\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class ComandaController extends Controller
{
    public function index(ComandaBillService $comandas): View
    {
        return view('comandas.index', [
            'overview' => $comandas->overview(),
            'customers' => Customer::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'phone']),
        ]);
    }

    public function open(Request $request, int $comanda, ComandaBillService $comandas): RedirectResponse
    {
        return $this->openComanda($request, $comanda, $comandas);
    }

    public function openManual(Request $request, ComandaBillService $comandas): RedirectResponse
    {
        $validated = $request->validate([
            'comanda_number' => ['required', 'integer', 'min:1', 'max:9999'],
            'customer_id' => ['nullable', 'exists:customers,id'],
        ]);

        return $this->openComanda($request, (int) $validated['comanda_number'], $comandas);
    }

    public function show(int $comanda, ComandaBillService $comandas, WaiterCartService $cart): View
    {
        $cart->setComandaNumber($comanda);

        $orders = $comandas->openOrdersForComanda($comanda);
        $categories = MenuCatalog::categories();
        $bill = $orders->isNotEmpty() ? $comandas->buildSummary($orders) : null;

        $linkedCustomer = ComandaCustomer::get($comanda);
        if ($linkedCustomer === null) {
            $fromOrder = $orders->first(fn ($order) => $order->customer_id);
            if ($fromOrder?->customer) {
                $linkedCustomer = [
                    'id' => $fromOrder->customer->id,
                    'name' => $fromOrder->customer->name,
                ];
            } elseif (filled($fromOrder?->customer_name)) {
                $linkedCustomer = [
                    'id' => 0,
                    'name' => (string) $fromOrder->customer_name,
                ];
            }
        }

        return view('comandas.show', [
            'bill' => $bill,
            'comanda' => $comanda,
            'categories' => $categories,
            'customers' => Customer::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'phone']),
            'linkedCustomer' => $linkedCustomer,
            'autoOpenPicker' => request()->boolean('add') || request()->boolean('picker'),
            'editing' => request()->boolean('edit'),
        ]);
    }

    public function updateCustomer(Request $request, int $comanda, ComandaBillService $comandas): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
        ]);

        $customer = isset($validated['customer_id'])
            ? Customer::query()->find($validated['customer_id'])
            : null;

        ComandaCustomer::bind($comanda, $customer);

        $orders = $comandas->openOrdersForComanda($comanda);
        foreach ($orders as $order) {
            $order->update([
                'customer_id' => $customer?->id,
                'customer_name' => $customer?->name,
            ]);
        }

        return redirect()
            ->route('comandas.show', ['comanda' => $comanda, 'edit' => 1])
            ->with('success', $customer
                ? 'Cliente '.$customer->name.' vinculado à comanda.'
                : 'Cliente removido da comanda.');
    }

    public function updateItem(
        Request $request,
        int $comanda,
        Order $order,
        OrderItem $item,
        ComandaBillService $comandas,
        InventoryService $inventory,
    ): RedirectResponse {
        try {
            $comandas->assertOpenOrderOnComanda($comanda, $order);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
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
            ->route('comandas.show', ['comanda' => $comanda, 'edit' => 1])
            ->with('success', 'Item atualizado.');
    }

    public function destroyItem(
        Request $request,
        int $comanda,
        Order $order,
        OrderItem $item,
        ComandaBillService $comandas,
        InventoryService $inventory,
    ): RedirectResponse {
        try {
            $comandas->assertOpenOrderOnComanda($comanda, $order);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
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
                $order->update(['status' => 'cancelled', 'total' => 0]);

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
            ->route('comandas.show', ['comanda' => $comanda, 'edit' => 1])
            ->with('success', 'Item removido.');
    }

    public function cancelOrder(
        Request $request,
        int $comanda,
        Order $order,
        ComandaBillService $comandas,
        InventoryService $inventory,
    ): RedirectResponse {
        try {
            $comandas->assertOpenOrderOnComanda($comanda, $order);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($order->inventory_deducted_at) {
            $inventory->restoreForOrder(
                $order->fresh(['items.product.recipe', 'items.productVariant.recipe']),
                $request->user()->id
            );
        }

        $order->update(['status' => 'cancelled']);

        return redirect()
            ->route('comandas.show', ['comanda' => $comanda, 'edit' => 1])
            ->with('success', 'Pedido '.$order->order_number.' cancelado.');
    }

    public function close(Request $request, int $comanda, ComandaBillService $comandas, OrderPrinterService $printer): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'in:'.implode(',', PaymentMethod::keys())],
        ]);

        try {
            $bill = $comandas->closeComanda($comanda, $validated['payment_method']);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        ComandaCustomer::forget($comanda);

        try {
            $printer->dispatchComandaBill($bill);
        } catch (\Throwable) {
            //
        }

        return redirect()->route('comandas.index')
            ->with('success', 'Comanda '.str_pad((string) $comanda, 3, '0', STR_PAD_LEFT).' fechada ('.PaymentMethod::label($validated['payment_method']).'). Total: R$ '.number_format($bill['total'], 2, ',', '.'));
    }

    private function openComanda(Request $request, int $comanda, ComandaBillService $comandas): RedirectResponse
    {
        if ($comanda < 1 || $comanda > $comandas->totalComandas()) {
            return back()->with('error', 'Número de comanda inválido.');
        }

        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
        ]);

        $customer = isset($validated['customer_id'])
            ? Customer::query()->find($validated['customer_id'])
            : null;

        if ($comandas->openOrdersForComanda($comanda)->isNotEmpty()) {
            if ($customer) {
                ComandaCustomer::bind($comanda, $customer);
            }

            return redirect()->route('comandas.show', $comanda)
                ->with('info', 'Comanda '.str_pad((string) $comanda, 3, '0', STR_PAD_LEFT).' já está em uso.');
        }

        ComandaCustomer::bind($comanda, $customer);

        $message = 'Comanda '.str_pad((string) $comanda, 3, '0', STR_PAD_LEFT).' aberta. Adicione os produtos.';
        if ($customer) {
            $message = 'Comanda '.str_pad((string) $comanda, 3, '0', STR_PAD_LEFT).' aberta para '.$customer->name.'. Adicione os produtos.';
        }

        return redirect()->route('comandas.show', ['comanda' => $comanda, 'add' => 1])
            ->with('success', $message);
    }
}
