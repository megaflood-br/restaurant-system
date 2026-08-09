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
            $printer->printOrder($order, $template);

            return back()->with('success', 'Comanda enviada para a impressora.');
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
