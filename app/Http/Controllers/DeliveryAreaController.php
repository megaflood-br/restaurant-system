<?php

namespace App\Http\Controllers;

use App\Models\DeliveryArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryAreaController extends Controller
{
    public function index(): View
    {
        $deliveryAreas = DeliveryArea::withCount('orders')->ordered()->paginate(15);

        return view('delivery-areas.index', compact('deliveryAreas'));
    }

    public function create(): View
    {
        return view('delivery-areas.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'min_km' => ['required', 'numeric', 'min:0', 'max:999'],
            'max_km' => ['nullable', 'numeric', 'min:0', 'max:999', 'gte:min_km'],
            'fee' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        DeliveryArea::create($validated);

        return redirect()->route('delivery-areas.index')->with('success', 'Região de entrega criada com sucesso.');
    }

    public function edit(DeliveryArea $deliveryArea): View
    {
        return view('delivery-areas.edit', compact('deliveryArea'));
    }

    public function update(Request $request, DeliveryArea $deliveryArea): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'min_km' => ['required', 'numeric', 'min:0', 'max:999'],
            'max_km' => ['nullable', 'numeric', 'min:0', 'max:999', 'gte:min_km'],
            'fee' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $deliveryArea->update($validated);

        return redirect()->route('delivery-areas.index')->with('success', 'Região de entrega atualizada com sucesso.');
    }

    public function destroy(DeliveryArea $deliveryArea): RedirectResponse
    {
        if ($deliveryArea->orders()->exists()) {
            return back()->with('error', 'Não é possível excluir uma região com pedidos vinculados. Desative-a em vez disso.');
        }

        $deliveryArea->delete();

        return redirect()->route('delivery-areas.index')->with('success', 'Região de entrega excluída com sucesso.');
    }
}
