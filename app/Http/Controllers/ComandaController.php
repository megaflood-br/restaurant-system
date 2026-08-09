<?php

namespace App\Http\Controllers;

use App\Services\ComandaBillService;
use App\Services\WaiterCartService;
use App\Services\OrderPrinterService;
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
        ]);
    }

    public function open(int $comanda, ComandaBillService $comandas): RedirectResponse
    {
        return $this->openComanda($comanda, $comandas);
    }

    public function openManual(Request $request, ComandaBillService $comandas): RedirectResponse
    {
        $validated = $request->validate([
            'comanda_number' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        return $this->openComanda((int) $validated['comanda_number'], $comandas);
    }

    public function show(int $comanda, ComandaBillService $comandas, WaiterCartService $cart): View
    {
        $cart->setComandaNumber($comanda);

        $orders = $comandas->openOrdersForComanda($comanda);
        $categories = MenuCatalog::categories();
        $bill = $orders->isNotEmpty() ? $comandas->buildSummary($orders) : null;

        return view('comandas.show', [
            'bill' => $bill,
            'comanda' => $comanda,
            'categories' => $categories,
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

        if (config('printing.enabled') && config('printing.driver') === 'network') {
            try {
                $printer->printComandaBill($bill);
            } catch (\Throwable) {
                //
            }
        }

        return redirect()->route('comandas.index')
            ->with('success', 'Comanda '.str_pad((string) $comanda, 3, '0', STR_PAD_LEFT).' fechada ('.PaymentMethod::label($validated['payment_method']).'). Total: R$ '.number_format($bill['total'], 2, ',', '.'));
    }

    private function openComanda(int $comanda, ComandaBillService $comandas): RedirectResponse
    {
        if ($comanda < 1 || $comanda > $comandas->totalComandas()) {
            return back()->with('error', 'Número de comanda inválido.');
        }

        if ($comandas->openOrdersForComanda($comanda)->isNotEmpty()) {
            return redirect()->route('comandas.show', $comanda)
                ->with('info', 'Comanda '.str_pad((string) $comanda, 3, '0', STR_PAD_LEFT).' já está em uso.');
        }

        return redirect()->route('comandas.show', ['comanda' => $comanda, 'add' => 1])
            ->with('success', 'Comanda '.str_pad((string) $comanda, 3, '0', STR_PAD_LEFT).' aberta. Adicione os produtos.');
    }
}
