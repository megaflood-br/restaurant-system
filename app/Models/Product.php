<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
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

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function hasVariants(): bool
    {
        if (! Schema::hasTable('product_variants')) {
            return false;
        }

        if ($this->relationLoaded('variants')) {
            return $this->variants->isNotEmpty();
        }

        return $this->variants()->exists();
    }

    public function scopeWithMenuRelations($query)
    {
        $query->with('recipe');

        if (Schema::hasTable('product_variants')) {
            $query->with(['variants' => fn ($variantQuery) => $variantQuery
                ->where('is_available', true)
                ->orderBy('sort_order')]);
        }

        return $query;
    }

    public function availableVariants()
    {
        return $this->variants()->where('is_available', true)->orderBy('sort_order');
    }

    public function displayPrice(): float
    {
        if ($this->hasVariants()) {
            $variants = $this->relationLoaded('variants')
                ? $this->variants->where('is_available', true)
                : $this->availableVariants()->get();

            return (float) ($variants->min('price') ?? $this->price);
        }

        return (float) $this->price;
    }

    public function priceLabel(): string
    {
        if ($this->hasVariants()) {
            $variants = $this->relationLoaded('variants')
                ? $this->variants->where('is_available', true)
                : $this->availableVariants()->get();

            $min = (float) ($variants->min('price') ?? $this->price);
            $max = (float) ($variants->max('price') ?? $min);

            if ($min === $max) {
                return number_format($min, 2, ',', '.');
            }

            return number_format($min, 2, ',', '.').' – '.number_format($max, 2, ',', '.');
        }

        return number_format((float) $this->price, 2, ',', '.');
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
