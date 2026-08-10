<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $with = ['category', 'recipe'];
        if (\App\Support\ProductVariants::enabled()) {
            $with[] = 'variants.recipe';
        }

        $products = Product::with($with)->latest()->paginate(10);

        return view('products.index', compact('products'));
    }

    public function create(): View
    {
        return view('products.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);

        $validated['is_available'] = $request->boolean('is_available', true);

        if ($request->boolean('has_variants')) {
            $validated['recipe_id'] = null;
            $validated['price'] = collect($request->input('variants', []))->min('price') ?? 0;
        } else {
            $validated['recipe_id'] = $request->integer('recipe_id') ?: null;
        }

        if ($request->hasFile('image')) {
            $validated['image'] = $this->storeImage($request->file('image'));
        }

        $product = DB::transaction(function () use ($request, $validated) {
            $product = Product::create($validated);

            if ($request->boolean('has_variants')) {
                $this->syncVariants($product, $request);
                $product->update(['price' => $product->variants()->min('price') ?? 0]);
            } else {
                $this->syncRecipeLink($product);
            }

            return $product;
        });

        return redirect()->route('products.index')->with('success', 'Produto criado com sucesso.');
    }

    public function edit(Product $product): View
    {
        $with = ['recipe'];
        if (\App\Support\ProductVariants::enabled()) {
            $with[] = 'variants.recipe';
        }

        return view('products.edit', [
            ...$this->formData($product),
            'product' => $product->load($with),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validateProduct($request, updating: true);

        $validated['is_available'] = $request->boolean('is_available');

        if ($request->boolean('has_variants')) {
            $validated['recipe_id'] = null;
            $validated['price'] = collect($request->input('variants', []))->min('price') ?? $product->price;
        } else {
            $validated['recipe_id'] = $request->integer('recipe_id') ?: null;
        }

        if ($request->boolean('remove_image')) {
            $this->deleteImage($product->image);
            $validated['image'] = null;
        } elseif ($request->hasFile('image')) {
            $this->deleteImage($product->image);
            $validated['image'] = $this->storeImage($request->file('image'));
        }

        DB::transaction(function () use ($request, $product, $validated) {
            $product->update($validated);

            if ($request->boolean('has_variants')) {
                $this->syncVariants($product, $request);
                $product->update(['price' => $product->variants()->min('price') ?? 0]);
            } else {
                $product->variants()->delete();
                $this->syncRecipeLink($product);
            }
        });

        return redirect()->route('products.index')->with('success', 'Produto atualizado com sucesso.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        DB::transaction(function () use ($product) {
            OrderItem::query()
                ->where('product_id', $product->id)
                ->whereNull('product_name')
                ->update(['product_name' => $product->name]);

            Recipe::query()->where('product_id', $product->id)->update(['product_id' => null]);

            $product->update(['recipe_id' => null]);

            $this->deleteImage($product->image);
            $product->delete();
        });

        return redirect()->route('products.index')->with('success', 'Produto excluído com sucesso.');
    }

    /** @return array<string, mixed> */
    private function formData(?Product $product = null): array
    {
        $variantRecipeIds = $product
            ? $product->variants()->pluck('recipe_id')->filter()->all()
            : [];

        return [
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'recipes' => Recipe::query()
                ->where('is_active', true)
                ->where(function ($query) use ($product, $variantRecipeIds) {
                    $query->whereNull('product_id');

                    if ($product) {
                        $query->orWhere('product_id', $product->id);
                    }

                    if ($variantRecipeIds !== []) {
                        $query->orWhereIn('id', $variantRecipeIds);
                    }
                })
                ->orderBy('name')
                ->get(),
        ];
    }

    private function syncVariants(Product $product, Request $request): void
    {
        $rows = $request->input('variants', []);
        $keepIds = [];

        foreach ($rows as $index => $row) {
            if (blank($row['label'] ?? null)) {
                continue;
            }

            $data = [
                'label' => trim((string) $row['label']),
                'price' => (float) ($row['price'] ?? 0),
                'recipe_id' => filled($row['recipe_id'] ?? null) ? (int) $row['recipe_id'] : null,
                'is_available' => filter_var($row['is_available'] ?? true, FILTER_VALIDATE_BOOL),
                'sort_order' => $index,
            ];

            if (! empty($row['id'])) {
                $variant = $product->variants()->whereKey($row['id'])->first();

                if ($variant) {
                    $variant->update($data);
                    $keepIds[] = $variant->id;

                    continue;
                }
            }

            $variant = $product->variants()->create($data);
            $keepIds[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $keepIds)->each(function (ProductVariant $variant) {
            $variant->delete();
        });
    }

    private function syncRecipeLink(Product $product): void
    {
        Recipe::query()
            ->where('product_id', $product->id)
            ->where('id', '!=', $product->recipe_id)
            ->update(['product_id' => null]);

        if ($product->recipe_id) {
            Recipe::query()->where('id', $product->recipe_id)->update(['product_id' => $product->id]);
        }
    }

    private function validateProduct(Request $request, bool $updating = false): array
    {
        $hasVariants = $request->boolean('has_variants');

        return $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'recipe_id' => [Rule::excludeIf($hasVariants), 'nullable', 'exists:recipes,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => [Rule::excludeIf($hasVariants), 'required', 'numeric', 'min:0'],
            'is_available' => ['boolean'],
            'has_variants' => ['boolean'],
            'variants' => [Rule::requiredIf($hasVariants), 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.label' => ['required_with:variants', 'string', 'max:50'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.recipe_id' => ['nullable', 'exists:recipes,id'],
            'variants.*.is_available' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
        ]);
    }

    private function storeImage(UploadedFile $file): string
    {
        return $file->store('products', 'public');
    }

    private function deleteImage(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
