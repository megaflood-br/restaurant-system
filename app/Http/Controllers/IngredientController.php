<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesBulkDestroy;
use App\Models\Ingredient;
use App\Models\StockCategory;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IngredientController extends Controller
{
    use HandlesBulkDestroy;

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

    public function prices(Request $request): View
    {
        $query = Ingredient::with(['stockCategory', 'lastPurchase'])
            ->orderBy('name');

        if ($request->filled('stock_category')) {
            $query->where('stock_category_id', $request->integer('stock_category'));
        }

        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.$request->string('q').'%');
        }

        $sort = $request->string('sort')->toString();
        if ($sort === 'cost_desc') {
            $query->reorder()->orderByDesc('cost_price')->orderBy('name');
        } elseif ($sort === 'cost_asc') {
            $query->reorder()->orderBy('cost_price')->orderBy('name');
        }

        $ingredients = $query->paginate(50)->withQueryString();
        $stockCategories = StockCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('ingredients.prices', compact('ingredients', 'stockCategories'));
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

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $this->bulkIds($request);
        $deleted = Ingredient::query()->whereIn('id', $ids)->delete();

        return $this->bulkResultRedirect('ingredients.index', $deleted, 0, 'item de estoque', 'itens de estoque');
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
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $costPrice = null;
        if ($validated['type'] === 'in' && $request->filled('cost_price')) {
            $costPrice = (float) $validated['cost_price'];
        }

        try {
            $inventory->manualMovement(
                $ingredient,
                $validated['type'],
                (float) $validated['quantity'],
                $validated['notes'] ?? null,
                $request->user()->id,
                $costPrice,
            );
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $message = 'Movimentação registrada com sucesso.';
        if ($costPrice !== null) {
            $message = 'Entrada registrada e preço de compra atualizado.';
        }

        return redirect()
            ->route('ingredients.movement', $ingredient)
            ->with('success', $message);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'stockCategories' => StockCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }
}
