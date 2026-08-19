<?php

namespace App\Http\Controllers;

use App\Services\MotoboyPayoutService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MotoboySettlementController extends Controller
{
    public function index(Request $request, MotoboyPayoutService $payouts): View
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date'))->timezone(config('app.timezone'))
            : today();

        $deliveredOnly = $request->boolean('delivered_only');
        $orders = $payouts->ordersForDate($date, $deliveredOnly);
        $summary = $payouts->summarize($orders);
        $settlement = $payouts->settlementForDate($date);

        $dailyRate = (float) old('daily_rate', $settlement?->daily_rate ?? 0);
        $totalPayout = round($summary['delivery_fees_total'] + $dailyRate, 2);

        return view('motoboy.index', [
            'date' => $date->toDateString(),
            'deliveredOnly' => $deliveredOnly,
            'orders' => $orders,
            'summary' => $summary,
            'settlement' => $settlement,
            'dailyRate' => $dailyRate,
            'totalPayout' => $totalPayout,
            'statusLabels' => [
                'pending' => 'Pendente',
                'preparing' => 'Preparando',
                'ready' => 'Pronto',
                'served' => 'Servido',
                'delivered' => 'Entregue',
                'cancelled' => 'Cancelado',
            ],
        ]);
    }

    public function store(Request $request, MotoboyPayoutService $payouts): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'mark_paid' => ['nullable', 'boolean'],
            'delivered_only' => ['nullable', 'boolean'],
        ]);

        $date = Carbon::parse($validated['date'])->timezone(config('app.timezone'));
        $deliveredOnly = $request->boolean('delivered_only');

        $payouts->saveSettlement(
            $date,
            (float) $validated['daily_rate'],
            $validated['notes'] ?? null,
            $request->boolean('mark_paid'),
            $request->user(),
            $deliveredOnly,
        );

        return redirect()
            ->route('motoboy.index', [
                'date' => $date->toDateString(),
                'delivered_only' => $deliveredOnly ? 1 : null,
            ])
            ->with('success', 'Apuração do motoboy salva.');
    }
}
