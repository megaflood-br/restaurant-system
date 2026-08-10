<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class ProductVariants
{
    public static function enabled(): bool
    {
        static $enabled = null;

        if ($enabled === null) {
            $enabled = Schema::hasTable('product_variants');
        }

        return $enabled;
    }

    /** @return array<int, string> */
    public static function variantIdRules(): array
    {
        $rules = ['nullable', 'integer'];

        if (self::enabled()) {
            $rules[] = 'exists:product_variants,id';
        }

        return $rules;
    }

    public static function loadProduct(int $id): \App\Models\Product
    {
        $query = \App\Models\Product::query();

        if (self::enabled()) {
            $query->with('variants');
        }

        return $query->findOrFail($id);
    }
}
