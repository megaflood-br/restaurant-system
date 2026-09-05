<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Services\CashFlowService;
use App\Support\CashCategory;
use App\Support\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceiroController extends Controller
{
    public function index(Request $request, CashFlowService $cashFlow): View
    {
        [$from, $to] = $this->resolvePeriod($request);

        $summary = $cashFlow->periodSummary($from, $to);
        $dayTotals = $cashFlow->rangeTotals($from, $to);

        return view('financeiro.index', [
            'summary' => $summary,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'isSingleDay' => $from->toDateString() === $to->toDateString(),
            'dayTotals' => $dayTotals,
            'paymentLabels' => PaymentMethod::labels() + ['nao_informado' => 'Não informado'],
        ]);
    }

    public function create(Request $request): View
    {
        $type = $request->string('type')->toString() === 'saida' ? 'saida' : 'entrada';

        return view('financeiro.create', [
            'type' => $type,
            'entradaCategories' => CashCategory::entradaLabels(),
            'saidaCategories' => CashCategory::saidaLabels(),
            'paymentMethods' => PaymentMethod::labels(),
        ]);
    }

    public function store(Request $request, CashFlowService $cashFlow): RedirectResponse
    {
        $type = $request->input('type') === 'saida' ? 'saida' : 'entrada';

        $validated = $request->validate([
            'type' => ['required', 'in:entrada,saida'],
            'category' => ['required', 'in:'.implode(',', CashCategory::keysForType($type))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'in:'.implode(',', PaymentMethod::keys())],
            'description' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $cashFlow->record([
            'type' => $validated['type'],
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'] ?? null,
            'description' => $validated['description'] ?? null,
            'occurred_at' => $validated['occurred_at'] ?? now(),
            'user_id' => $request->user()->id,
            'source' => 'manual',
        ]);

        $date = isset($validated['occurred_at'])
            ? Carbon::parse($validated['occurred_at'])->toDateString()
            : today()->toDateString();

        return redirect()
            ->route('financeiro.index', ['from' => $date, 'to' => $date])
            ->with('success', 'Lançamento registrado no fluxo de caixa.');
    }

    public function destroy(Request $request, CashMovement $financeiro, CashFlowService $cashFlow): RedirectResponse
    {
        $date = $financeiro->reference_date?->toDateString() ?? today()->toDateString();

        try {
            $cashFlow->deleteManual($financeiro);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $query = $this->periodQueryFromRequest($request, $date);

        return redirect()
            ->route('financeiro.index', $query)
            ->with('success', 'Lançamento manual excluído.');
    }

    public function syncSales(Request $request, CashFlowService $cashFlow): RedirectResponse
    {
        [$from, $to] = $this->resolvePeriod($request);

        $result = $cashFlow->syncDeliveredSalesForPeriod($from, $to, $request->user()->id);
        $gap = $cashFlow->deliveredSalesGapForPeriod($from, $to);

        if ($result['created'] > 0) {
            $message = sprintf(
                'Sincronizadas %d venda(s) — R$ %s adicionados às entradas.',
                $result['created'],
                number_format($result['amount'], 2, ',', '.')
            );
        } elseif ($gap > 0.009) {
            $message = sprintf(
                'Nenhum lançamento novo criado, mas ainda há cerca de R$ %s de pedidos entregues sem cobertura completa no caixa. Confira se há lançamentos manuais a ajustar.',
                number_format($gap, 2, ',', '.')
            );
        } else {
            $message = $from->toDateString() === $to->toDateString()
                ? 'Nenhuma venda faltando no caixa para este dia.'
                : 'Nenhuma venda faltando no caixa para este período.';
        }

        return redirect()
            ->route('financeiro.index', [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ])
            ->with('success', $message);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function resolvePeriod(Request $request): array
    {
        $tz = config('app.timezone');

        if ($request->filled('from') || $request->filled('to')) {
            $from = Carbon::parse($request->input('from', $request->input('to')), $tz)->startOfDay();
            $to = Carbon::parse($request->input('to', $request->input('from')), $tz)->startOfDay();
        } elseif ($request->filled('date')) {
            $from = Carbon::parse($request->string('date'), $tz)->startOfDay();
            $to = $from->copy();
        } else {
            $from = today($tz)->startOfDay();
            $to = $from->copy();
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        return [$from, $to];
    }

    /** @return array{from: string, to: string} */
    private function periodQueryFromRequest(Request $request, string $fallbackDate): array
    {
        if ($request->filled('from') || $request->filled('to')) {
            $from = $request->input('from', $request->input('to', $fallbackDate));
            $to = $request->input('to', $request->input('from', $fallbackDate));

            return ['from' => $from, 'to' => $to];
        }

        return ['from' => $fallbackDate, 'to' => $fallbackDate];
    }
}
