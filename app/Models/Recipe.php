<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Recipe extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'description',
        'image',
        'preparation_method',
        'yield_portions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'yield_portions' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->image) {
                return null;
            }

            return Storage::disk('public')->url($this->image);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'recipe_ingredient')
            ->withPivot('quantity');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function totalCost(): float
    {
        return $this->ingredients->sum(function (Ingredient $ingredient) {
            return $ingredient->lineCost((float) $ingredient->pivot->quantity);
        });
    }

    public function costPerPortion(): float
    {
        $yield = max(1, (int) $this->yield_portions);

        return $this->totalCost() / $yield;
    }
}
