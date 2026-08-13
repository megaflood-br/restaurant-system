<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesBulkDestroy;
use App\Models\StockCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockCategoryController extends Controller
{
    use HandlesBulkDestroy;

    public function index(): View
    {
        $categories = StockCategory::withCount('ingredients')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15);

        return view('stock-categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('stock-categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ]);

        StockCategory::create([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('stock-categories.index')->with('success', 'Categoria de estoque criada.');
    }

    public function edit(StockCategory $stockCategory): View
    {
        return view('stock-categories.edit', ['category' => $stockCategory]);
    }

    public function update(Request $request, StockCategory $stockCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ]);

        $stockCategory->update([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('stock-categories.index')->with('success', 'Categoria de estoque atualizada.');
    }

    public function destroy(StockCategory $stockCategory): RedirectResponse
    {
        $this->deleteStockCategory($stockCategory);

        return redirect()->route('stock-categories.index')->with('success', 'Categoria de estoque excluída.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $this->bulkIds($request);
        $deleted = 0;

        foreach (StockCategory::query()->whereIn('id', $ids)->get() as $category) {
            $this->deleteStockCategory($category);
            $deleted++;
        }

        return $this->bulkResultRedirect('stock-categories.index', $deleted, 0, 'categoria de estoque', 'categorias de estoque');
    }

    private function deleteStockCategory(StockCategory $stockCategory): void
    {
        $stockCategory->ingredients()->update(['stock_category_id' => null]);
        $stockCategory->delete();
    }
}
