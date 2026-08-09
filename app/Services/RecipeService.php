<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class RecipeService
{
    /** @return array<string, mixed> */
    public function formData(?Recipe $recipe = null): array
    {
        $ingredients = Ingredient::with('stockCategory')->orderBy('name')->get();

        return [
            'stockItems' => $ingredients->groupBy(fn ($item) => $item->stockCategory?->name ?? 'Sem categoria'),
            'ingredientMeta' => $ingredients->mapWithKeys(fn ($item) => [
                $item->id => [
                    'recipeLabel' => $item->recipeUnitLabel(),
                    'name' => $item->name,
                    'unitCost' => $item->unitCost(),
                    'stockUnit' => $item->unit,
                ],
            ]),
            'recipeLines' => $recipe ? $this->linesForForm($recipe) : [['ingredient_id' => '', 'quantity' => '']],
        ];
    }

    /** @return list<array{ingredient_id: int|string, quantity: float|string}> */
    public function linesForForm(Recipe $recipe): array
    {
        $lines = $recipe->ingredients->map(fn (Ingredient $ingredient) => [
            'ingredient_id' => $ingredient->id,
            'quantity' => $ingredient->recipeQuantityFromStock((float) $ingredient->pivot->quantity),
        ])->values()->all();

        return $lines ?: [['ingredient_id' => '', 'quantity' => '']];
    }

    /** @return Collection<string, Collection<int, Ingredient>> */
    public function groupedForPrint(Recipe $recipe): Collection
    {
        return $recipe->ingredients
            ->sortBy([
                fn (Ingredient $ingredient) => $ingredient->stockCategory?->sort_order ?? 999,
                fn (Ingredient $ingredient) => $ingredient->name,
            ])
            ->groupBy(fn (Ingredient $ingredient) => $ingredient->stockCategory?->name ?? 'Sem categoria');
    }

    /** @return array<string, mixed> */
    public function validateRecipe(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'preparation_method' => ['nullable', 'string'],
            'yield_portions' => ['required', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
        ]);
    }

    /** @return list<array{ingredient_id: int, quantity: float}> */
    public function validateAndConvertLines(Request $request): array
    {
        $validated = $request->validate([
            'recipe' => ['nullable', 'array'],
            'recipe.*.ingredient_id' => ['nullable', 'exists:ingredients,id'],
            'recipe.*.quantity' => ['nullable', 'numeric', 'min:0.001'],
        ]);

        $ingredients = Ingredient::whereIn(
            'id',
            collect($validated['recipe'] ?? [])->pluck('ingredient_id')->filter()
        )->get()->keyBy('id');

        $lines = [];

        foreach ($validated['recipe'] ?? [] as $row) {
            if (empty($row['ingredient_id']) || empty($row['quantity'])) {
                continue;
            }

            $ingredient = $ingredients->get((int) $row['ingredient_id']);

            if (! $ingredient) {
                continue;
            }

            $lines[] = [
                'ingredient_id' => (int) $row['ingredient_id'],
                'quantity' => $ingredient->stockQuantityFromRecipe((float) $row['quantity']),
            ];
        }

        return $lines;
    }

    /** @param list<array{ingredient_id: int, quantity: float}> $lines */
    public function syncIngredients(Recipe $recipe, array $lines): void
    {
        $sync = [];

        foreach ($lines as $row) {
            $sync[$row['ingredient_id']] = ['quantity' => $row['quantity']];
        }

        $recipe->ingredients()->sync($sync);
    }

    public function syncProductLink(Recipe $recipe, ?int $productId): void
    {
        Product::query()->where('recipe_id', $recipe->id)->update(['recipe_id' => null]);

        if ($productId) {
            Product::query()->where('id', $productId)->update(['recipe_id' => $recipe->id]);
            Recipe::query()
                ->where('product_id', $productId)
                ->where('id', '!=', $recipe->id)
                ->update(['product_id' => null]);
        }

        $recipe->update(['product_id' => $productId]);
    }

    public function storeImage(UploadedFile $file): string
    {
        return $file->store('recipes', 'public');
    }

    public function deleteImage(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /** @param array<string, mixed> $validated */
    public function applyImageChanges(Request $request, Recipe $recipe, array &$validated): void
    {
        if ($request->boolean('remove_image')) {
            $this->deleteImage($recipe->image);
            $validated['image'] = null;
        } elseif ($request->hasFile('image')) {
            $this->deleteImage($recipe->image);
            $validated['image'] = $this->storeImage($request->file('image'));
        } else {
            unset($validated['image']);
        }
    }
}
