<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Collection;

class MenuCatalog
{
    public static function categories(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->with(['products' => fn ($query) => $query->where('is_available', true)->with(['recipe', 'variants' => fn ($q) => $q->where('is_available', true)->orderBy('sort_order')])->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $category) => $category->products->isNotEmpty());
    }
}
