<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\StockCategory;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IngredientController extends Controller
{
    public function index(Request $request): View
    {
        $query = Ingredient::with('stockCategory')->orderBy('name');

        if ($request->filled('stock_category')) {
            $query->where('stock_category_id', $request->integer('stock_category'));
        }

        $ingredients = $query->paginate(15)->withQueryString();
        $stockCategories = StockCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('ingredients.index', compact('ingredients', 'stockCategories'));
    }

    public function create(): View
    {
        return view('ingredients.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'stock_category_id' => ['nullable', 'exists:stock_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:20'],
            'package_size' => ['nullable', 'numeric', 'min:0.001'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'current_stock' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
        ]);

        Ingredient::create($validated);

        return redirect()->route('ingredients.index')->with('success', 'Item de estoque criado com sucesso.');
    }

    public function edit(Ingredient $ingredient): View
    {
        return view('ingredients.edit', [
            'ingredient' => $ingredient,
            ...$this->formData(),
        ]);
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $validated = $request->validate([
            'stock_category_id' => ['nullable', 'exists:stock_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:20'],
            'package_size' => ['nullable', 'numeric', 'min:0.001'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
        ]);

        $ingredient->update($validated);

        return redirect()->route('ingredients.index')->with('success', 'Item de estoque atualizado com sucesso.');
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        $ingredient->delete();

        return redirect()->route('ingredients.index')->with('success', 'Item de estoque excluído.');
    }

    public function movementForm(Ingredient $ingredient): View
    {
        $movements = $ingredient->movements()->with(['user', 'order'])->latest()->limit(15)->get();

        return view('ingredients.movement', compact('ingredient', 'movements'));
    }

    public function storeMovement(Request $request, Ingredient $ingredient, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:in,out'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $inventory->manualMovement(
                $ingredient,
                $validated['type'],
                (float) $validated['quantity'],
                $validated['notes'] ?? null,
                $request->user()->id,
            );
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('ingredients.movement', $ingredient)
            ->with('success', 'Movimentação registrada com sucesso.');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'stockCategories' => StockCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }
}
