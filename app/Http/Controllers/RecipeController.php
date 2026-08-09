<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Recipe;
use App\Services\RecipeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function __construct(private RecipeService $recipes)
    {
    }

    public function index(): View
    {
        $recipes = Recipe::with(['product.category', 'ingredients'])
            ->withCount('ingredients')
            ->latest()
            ->paginate(12);

        return view('recipes.index', compact('recipes'));
    }

    public function create(): View
    {
        return view('recipes.create', [
            ...$this->recipes->formData(),
            'products' => $this->availableProducts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->recipes->validateRecipe($request);
        $lines = $this->recipes->validateAndConvertLines($request);

        $validated['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('image')) {
            $validated['image'] = $this->recipes->storeImage($request->file('image'));
        }

        $recipe = Recipe::create($validated);
        $this->recipes->syncIngredients($recipe, $lines);
        $this->recipes->syncProductLink($recipe, $request->integer('product_id') ?: null);

        return redirect()
            ->route('recipes.edit', $recipe)
            ->with('success', 'Ficha técnica criada com sucesso.');
    }

    public function edit(Recipe $recipe): View
    {
        $recipe->load(['product.category', 'ingredients.stockCategory']);

        return view('recipes.edit', [
            'recipe' => $recipe,
            ...$this->recipes->formData($recipe),
            'products' => $this->availableProducts($recipe),
        ]);
    }

    public function update(Request $request, Recipe $recipe): RedirectResponse
    {
        $validated = $this->recipes->validateRecipe($request, updating: true);
        $lines = $this->recipes->validateAndConvertLines($request);

        $validated['is_active'] = $request->boolean('is_active');
        $this->recipes->applyImageChanges($request, $recipe, $validated);

        $recipe->update($validated);
        $this->recipes->syncIngredients($recipe, $lines);
        $this->recipes->syncProductLink($recipe, $request->integer('product_id') ?: null);

        return redirect()
            ->route('recipes.edit', $recipe)
            ->with('success', 'Ficha técnica atualizada com sucesso.');
    }

    public function destroy(Recipe $recipe): RedirectResponse
    {
        Product::query()->where('recipe_id', $recipe->id)->update(['recipe_id' => null]);
        $this->recipes->deleteImage($recipe->image);
        $recipe->delete();

        return redirect()
            ->route('recipes.index')
            ->with('success', 'Ficha técnica excluída.');
    }

    public function print(Recipe $recipe): View
    {
        $recipe->load(['product.category', 'ingredients.stockCategory']);
        $groupedIngredients = $this->recipes->groupedForPrint($recipe);

        return view('recipes.print', compact('recipe', 'groupedIngredients'));
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Product> */
    private function availableProducts(?Recipe $recipe = null)
    {
        return Product::query()
            ->with('category')
            ->where(function ($query) use ($recipe) {
                $query->whereNull('recipe_id');

                if ($recipe) {
                    $query->orWhere('recipe_id', $recipe->id);
                }
            })
            ->orderBy('name')
            ->get();
    }
}
