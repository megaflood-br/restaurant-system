<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\MenuCatalog;
use Illuminate\Http\JsonResponse;

class MenuApiController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = MenuCatalog::categories();
        $index = 1;

        $data = $categories->map(function ($category) use (&$index) {
            $products = $category->products->map(function ($product) use (&$index) {
                $code = $index++;

                return [
                    'code' => $code,
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => (float) $product->displayPrice(),
                    'price_formatted' => $product->priceLabel(),
                    'has_variants' => $product->hasVariants(),
                    'variants' => $product->variants->map(fn ($variant) => [
                        'id' => $variant->id,
                        'label' => $variant->label,
                        'price' => (float) $variant->price,
                        'price_formatted' => number_format((float) $variant->price, 2, ',', '.'),
                    ])->values(),
                    'image_url' => $product->image_url,
                    'is_available' => (bool) $product->is_available,
                ];
            });

            return [
                'id' => $category->id,
                'name' => $category->name,
                'products' => $products->values(),
            ];
        });

        return response()->json(['data' => $data->values()]);
    }

    public function todayImage(): JsonResponse
    {
        return response()->json([
            'data' => [
                'day' => \App\Support\WeeklyMenuImages::todayKey(),
                'day_label' => \App\Support\WeeklyMenuImages::labels()[\App\Support\WeeklyMenuImages::todayKey()],
                'image_url' => \App\Support\WeeklyMenuImages::urlForToday(),
            ],
        ]);
    }
}
