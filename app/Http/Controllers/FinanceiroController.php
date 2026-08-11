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
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date'))->timezone(config('app.timezone'))
            : today();

        $summary = $cashFlow->dailySummary($date);
        $weekFrom = $date->copy()->subDays(6)->startOfDay();
        $weekTotals = $cashFlow->rangeTotals($weekFrom, $date->copy()->endOfDay());

        return view('financeiro.index', [
            'summary' => $summary,
            'date' => $date->toDateString(),
            'weekTotals' => $weekTotals,
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
            ->route('financeiro.index', ['date' => $date])
            ->with('success', 'Lançamento registrado no fluxo de caixa.');
    }

    public function destroy(CashMovement $financeiro, CashFlowService $cashFlow): RedirectResponse
    {
        $date = $financeiro->reference_date?->toDateString() ?? today()->toDateString();

        try {
            $cashFlow->deleteManual($financeiro);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('financeiro.index', ['date' => $date])
            ->with('success', 'Lançamento manual excluído.');
    }
}
