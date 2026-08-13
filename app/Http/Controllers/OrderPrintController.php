<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderPrinterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderPrintController extends Controller
{
    public function show(Order $order, Request $request): View
    {
        $order->load('items.product', 'customer', 'deliveryArea', 'user');

        return view('orders.print', [
            'order' => $order,
            'template' => $request->query('template', 'kitchen'),
            'autoprint' => $request->boolean('autoprint'),
        ]);
    }

    public function network(Order $order, OrderPrinterService $printer, Request $request): RedirectResponse
    {
        $template = $request->input('template', 'kitchen');

        try {
            if (! $printer->usesServerSidePrint()) {
                return back()->with('error', 'Ative o modo Rede IP ou Agente local em Configurações → Impressão.');
            }

            $printer->printOrder($order, $template);

            $message = config('printing.driver') === 'agent'
                ? 'Comanda enfileirada para o agente local imprimir.'
                : 'Comanda enviada para a impressora.';

            return back()->with('success', $message);
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
