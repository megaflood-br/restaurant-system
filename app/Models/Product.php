<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'recipe_id',
        'name',
        'description',
        'image',
        'price',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $path = $this->recipe?->image ?? $this->image;

            if (! $path) {
                return null;
            }

            return Storage::disk('public')->url($path);
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'product_ingredient')
            ->withPivot('quantity');
    }

    public function foodCost(): float
    {
        return $this->recipe?->costPerPortion() ?? 0;
    }

    public function margin(): float
    {
        return (float) $this->price - $this->foodCost();
    }
}
