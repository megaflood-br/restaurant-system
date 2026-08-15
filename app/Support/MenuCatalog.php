<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Collection;

class MenuCatalog
{
    public static function categories(?string $day = null): Collection
    {
        $day = $day ?? WeeklyMenuImages::todayKey();

        return Category::query()
            ->where('is_active', true)
            ->availableOnDay($day)
            ->with(['products' => fn ($query) => $query->where('is_available', true)->withMenuRelations()->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $category) => $category->products->isNotEmpty());
    }
}
