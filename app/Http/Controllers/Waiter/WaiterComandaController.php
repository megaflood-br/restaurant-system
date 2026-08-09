<?php

namespace App\Http\Controllers\Waiter;

use App\Http\Controllers\Controller;
use App\Services\ComandaBillService;
use App\Services\OrderPrinterService;
use App\Services\WaiterCartService;
use App\Support\PaymentMethod;
use App\Support\MenuCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class WaiterComandaController extends Controller
{
    public function index(ComandaBillService $comandas): View
    {
        return view('waiter.comandas', [
            'overview' => $comandas->overview(),
        ]);
    }

    public function open(int $comanda, WaiterCartService $cart, ComandaBillService $comandas): RedirectResponse
    {
        if ($comanda < 1 || $comanda > $comandas->totalComandas()) {
            return back()->with('error', 'Número de comanda inválido.');
        }

        $cart->clearItems();
        $cart->setComandaNumber($comanda);

        return redirect()->route('waiter.comandas.show', ['comanda' => $comanda, 'add' => 1])
            ->with('success', 'Comanda '.str_pad((string) $comanda, 3, '0', STR_PAD_LEFT).' aberta.');
    }

    public function show(int $comanda, ComandaBillService $comandas): View
    {
        $orders = $comandas->openOrdersForComanda($comanda);
        $categories = MenuCatalog::categories();
        $bill = $orders->isNotEmpty() ? $comandas->buildSummary($orders) : null;

        return view('waiter.comanda-bill', [
            'bill' => $bill,
            'comanda' => $comanda,
            'categories' => $categories,
            'autoOpenPicker' => request()->boolean('add'),
        ]);
    }

    public function close(Request $request, int $comanda, ComandaBillService $comandas, OrderPrinterService $printer, WaiterCartService $cart): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'in:'.implode(',', PaymentMethod::keys())],
        ]);

        try {
            $bill = $comandas->closeComanda($comanda, $validated['payment_method']);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        if ((int) $cart->all()['comanda_number'] === $comanda) {
            $cart->clearItems();
            $cart->setComandaNumber(null);
        }

        session([
            'waiter_closed_comanda' => [
                'comanda_number' => $comanda,
                'total' => $bill['total'],
                'orders_count' => $bill['orders_count'],
                'payment_method' => $validated['payment_method'],
            ],
            'waiter_closed_bill' => $bill,
        ]);

        if (config('printing.enabled') && config('printing.driver') === 'network') {
            try {
                $printer->printComandaBill($bill);
            } catch (\Throwable) {
                //
            }

            return redirect()->route('waiter.comandas.closed');
        }

        if (config('printing.enabled') && config('printing.driver') === 'browser') {
            return redirect()->route('waiter.comandas.autoprint', $comanda);
        }

        return redirect()->route('waiter.comandas.closed');
    }

    public function autoprint(int $comanda): View|RedirectResponse
    {
        $bill = session('waiter_closed_bill');

        if (! $bill || ($bill['comanda_number'] ?? null) !== $comanda) {
            return redirect()->route('waiter.comandas.index');
        }

        return view('waiter.comanda-autoprint', compact('bill', 'comanda'));
    }

    public function closed(): View|RedirectResponse
    {
        $closed = session('waiter_closed_comanda');

        if (! $closed) {
            return redirect()->route('waiter.comandas.index');
        }

        session()->forget(['waiter_closed_comanda', 'waiter_closed_bill']);

        return view('waiter.comanda-closed', ['closed' => $closed]);
    }
}
