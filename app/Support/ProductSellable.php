<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class ProductSellable
{
    /** @return array{product: Product, variant: ?ProductVariant, price: float, name: string, variant_label: ?string} */
    public static function resolve(Product $product, ?int $variantId = null): array
    {
        $product->loadMissing(['variants' => fn ($query) => $query->where('is_available', true)->orderBy('sort_order')]);

        if ($product->variants->isNotEmpty()) {
            if (! $variantId) {
                throw ValidationException::withMessages([
                    'variant_id' => 'Selecione o tamanho ou variação deste produto.',
                ]);
            }

            $variant = $product->variants->firstWhere('id', $variantId);

            if (! $variant) {
                throw ValidationException::withMessages([
                    'variant_id' => 'Variação inválida para este produto.',
                ]);
            }

            return [
                'product' => $product,
                'variant' => $variant,
                'price' => (float) $variant->price,
                'name' => $product->name.' ('.$variant->label.')',
                'variant_label' => $variant->label,
            ];
        }

        if ($variantId) {
            throw ValidationException::withMessages([
                'variant_id' => 'Este produto não possui variações.',
            ]);
        }

        return [
            'product' => $product,
            'variant' => null,
            'price' => (float) $product->price,
            'name' => $product->name,
            'variant_label' => null,
        ];
    }

    /** @return array<string, mixed> */
    public static function orderItemAttributes(Product $product, int $quantity, ?int $variantId = null, ?string $notes = null): array
    {
        $resolved = self::resolve($product, $variantId);
        $price = $resolved['price'];

        return [
            'product_id' => $product->id,
            'product_variant_id' => $resolved['variant']?->id,
            'variant_label' => $resolved['variant_label'],
            'product_name' => $resolved['name'],
            'quantity' => $quantity,
            'unit_price' => $price,
            'subtotal' => $price * $quantity,
            'notes' => $notes,
        ];
    }
}
