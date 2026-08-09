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
                    'price' => (float) $product->price,
                    'price_formatted' => number_format((float) $product->price, 2, ',', '.'),
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
}
