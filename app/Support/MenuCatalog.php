<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Collection;

class MenuCatalog
{
    public static function categories(?string $day = null): Collection
    {
        $day = $day ?? WeeklyMenuImages::todayKey();

        $categories = Category::query()
            ->where('is_active', true)
            ->availableOnDay($day)
            ->with(['products' => fn ($query) => $query->where('is_available', true)->withMenuRelations()->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $category) => $category->products->isNotEmpty());

        return self::sortForDisplay($categories);
    }

    /** Pratos da semana (dias restritos) primeiro; bebidas e categorias fixas por último. */
    private static function sortForDisplay(Collection $categories): Collection
    {
        return $categories
            ->sort(function (Category $a, Category $b) {
                $aAlways = $a->isAlwaysAvailableCategory();
                $bAlways = $b->isAlwaysAvailableCategory();

                if ($aAlways !== $bAlways) {
                    return $aAlways <=> $bAlways;
                }

                return strcasecmp($a->name, $b->name);
            })
            ->values();
    }
}
