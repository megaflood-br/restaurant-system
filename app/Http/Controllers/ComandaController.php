<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\ComandaBillService;
use App\Services\WaiterCartService;
use App\Services\OrderPrinterService;
use App\Support\ComandaCustomer;
use App\Support\MenuCatalog;
use App\Support\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'linkedCustomer' => $linkedCustomer,
            'autoOpenPicker' => request()->boolean('add') || request()->boolean('picker'),
        ]);
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
