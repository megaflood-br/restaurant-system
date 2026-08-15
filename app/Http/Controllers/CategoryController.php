<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesBulkDestroy;
use App\Models\Category;
use App\Support\WeeklyMenuImages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use HandlesBulkDestroy;

    public function index(): View
    {
        $categories = Category::withCount('products')->latest()->paginate(10);

        return view('categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('categories.create', [
            'weekdayLabels' => WeeklyMenuImages::labels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedCategory($request);

        Category::create($validated);

        return redirect()->route('categories.index')->with('success', 'Categoria criada com sucesso.');
    }

    public function edit(Category $category): View
    {
        return view('categories.edit', [
            'category' => $category,
            'weekdayLabels' => WeeklyMenuImages::labels(),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $this->validatedCategory($request);

        $category->update($validated);

        return redirect()->route('categories.index')->with('success', 'Categoria atualizada com sucesso.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'Não é possível excluir uma categoria com produtos vinculados.');
        }

        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Categoria excluída com sucesso.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $this->bulkIds($request);
        $deleted = 0;
        $skipped = 0;

        foreach (Category::query()->whereIn('id', $ids)->get() as $category) {
            if ($category->products()->exists()) {
                $skipped++;

                continue;
            }

            $category->delete();
            $deleted++;
        }

        return $this->bulkResultRedirect('categories.index', $deleted, $skipped, 'categoria', 'categorias');
    }

    /** @return array{name: string, description: ?string, is_active: bool, available_days: ?array} */
    private function validatedCategory(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'available_days' => ['nullable', 'array'],
            'available_days.*' => ['string', Rule::in(WeeklyMenuImages::DAYS)],
        ]);

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'available_days' => Category::normalizeDaysInput($validated['available_days'] ?? null),
        ];
    }
}
