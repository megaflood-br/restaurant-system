<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::with(['category', 'recipe'])->latest()->paginate(10);

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
        $validated['recipe_id'] = $request->integer('recipe_id') ?: null;

        if ($request->hasFile('image')) {
            $validated['image'] = $this->storeImage($request->file('image'));
        }

        $product = Product::create($validated);
        $this->syncRecipeLink($product);

        return redirect()->route('products.index')->with('success', 'Produto criado com sucesso.');
    }

    public function edit(Product $product): View
    {
        return view('products.edit', [
            ...$this->formData($product),
            'product' => $product->load('recipe'),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validateProduct($request, updating: true);

        $validated['is_available'] = $request->boolean('is_available');
        $validated['recipe_id'] = $request->integer('recipe_id') ?: null;

        if ($request->boolean('remove_image')) {
            $this->deleteImage($product->image);
            $validated['image'] = null;
        } elseif ($request->hasFile('image')) {
            $this->deleteImage($product->image);
            $validated['image'] = $this->storeImage($request->file('image'));
        }

        $product->update($validated);
        $this->syncRecipeLink($product);

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
        return [
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'recipes' => Recipe::query()
                ->where('is_active', true)
                ->where(function ($query) use ($product) {
                    $query->whereNull('product_id');

                    if ($product) {
                        $query->orWhere('product_id', $product->id);
                    }
                })
                ->orderBy('name')
                ->get(),
        ];
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
        return $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'recipe_id' => ['nullable', 'exists:recipes,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_available' => ['boolean'],
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
