<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ingredient extends Model
{
    protected $fillable = [
        'stock_category_id',
        'name',
        'unit',
        'package_size',
        'cost_price',
        'current_stock',
        'minimum_stock',
    ];

    protected function casts(): array
    {
        return [
            'package_size' => 'decimal:3',
            'cost_price' => 'decimal:2',
            'current_stock' => 'decimal:2',
            'minimum_stock' => 'decimal:2',
        ];
    }

    public function stockCategory(): BelongsTo
    {
        return $this->belongsTo(StockCategory::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_ingredient')
            ->withPivot('quantity');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->current_stock <= $this->minimum_stock;
    }

    /** @param  array{q?: string, stock_category?: int|string|null, stock?: string|null, price?: string|null, sort?: string|null}  $filters */
    public function scopeFilteredForIndex(Builder $query, array $filters): Builder
    {
        if (filled($filters['q'] ?? null)) {
            $query->where('name', 'like', '%'.$filters['q'].'%');
        }

        if (filled($filters['stock_category'] ?? null)) {
            $query->where('stock_category_id', (int) $filters['stock_category']);
        }

        match ($filters['stock'] ?? null) {
            'low' => $query->whereColumn('current_stock', '<=', 'minimum_stock'),
            'ok' => $query->whereColumn('current_stock', '>', 'minimum_stock'),
            'zero' => $query->where('current_stock', '<=', 0),
            default => null,
        };

        match ($filters['price'] ?? null) {
            'with' => $query->whereNotNull('cost_price')->where('cost_price', '>', 0),
            'without' => $query->where(function (Builder $inner): void {
                $inner->whereNull('cost_price')->orWhere('cost_price', '<=', 0);
            }),
            default => null,
        };

        $query->reorder();

        match ($filters['sort'] ?? null) {
            'name_desc' => $query->orderByDesc('name'),
            'stock_asc' => $query->orderBy('current_stock')->orderBy('name'),
            'stock_desc' => $query->orderByDesc('current_stock')->orderBy('name'),
            'minimum_asc' => $query->orderBy('minimum_stock')->orderBy('name'),
            default => $query->orderBy('name'),
        };

        return $query;
    }

    public function recipeUnitLabel(): string
    {
        return match ($this->unit) {
            'kg', 'g' => 'g',
            'L', 'ml' => 'ml',
            default => 'un',
        };
    }

    public function stockQuantityFromRecipe(float $recipeQuantity): float
    {
        return match ($this->unit) {
            'kg' => $recipeQuantity / 1000,
            'g' => $recipeQuantity,
            'L' => $recipeQuantity / 1000,
            'ml' => $recipeQuantity,
            default => $recipeQuantity,
        };
    }

    public function recipeQuantityFromStock(float $stockQuantity): float
    {
        return match ($this->unit) {
            'kg' => $stockQuantity * 1000,
            'g' => $stockQuantity,
            'L' => $stockQuantity * 1000,
            'ml' => $stockQuantity,
            default => $stockQuantity,
        };
    }

    public function formatRecipeQuantity(float $stockQuantity): string
    {
        $value = $this->recipeQuantityFromStock($stockQuantity);
        $label = $this->recipeUnitLabel();

        if ($label === 'g' || $label === 'ml') {
            return number_format($value, 0, ',', '.').' '.$label;
        }

        return number_format($value, 2, ',', '.').' '.$label;
    }

    public function packageSizeLabel(): string
    {
        return match ($this->unit) {
            'kg', 'g' => 'kg',
            'L', 'ml' => 'L',
            default => 'un',
        };
    }

    public function unitCost(): float
    {
        if (! $this->package_size || ! $this->cost_price || (float) $this->package_size <= 0) {
            return 0;
        }

        $stockUnitsInPackage = match ($this->unit) {
            'kg' => (float) $this->package_size,
            'g' => (float) $this->package_size * 1000,
            'L' => (float) $this->package_size,
            'ml' => (float) $this->package_size * 1000,
            default => (float) $this->package_size,
        };

        if ($stockUnitsInPackage <= 0) {
            return 0;
        }

        return round((float) $this->cost_price / $stockUnitsInPackage, 6);
    }

    public function lineCost(float $stockQuantity): float
    {
        return round($stockQuantity * $this->unitCost(), 2);
    }

    public function formattedUnitCost(): string
    {
        return 'R$ '.number_format($this->unitCost(), 2, ',', '.').' / '.$this->unit;
    }

    public function formattedPackageCost(): string
    {
        if (! $this->package_size || ! $this->cost_price) {
            return '—';
        }

        return number_format((float) $this->package_size, 2, ',', '.').' '.$this->packageSizeLabel()
            .' · R$ '.number_format((float) $this->cost_price, 2, ',', '.');
    }

    public function lastPurchaseMovement(): ?InventoryMovement
    {
        if ($this->relationLoaded('lastPurchase')) {
            return $this->getRelation('lastPurchase');
        }

        return $this->movements()
            ->where('type', 'in')
            ->whereNotNull('cost_price')
            ->latest('id')
            ->first();
    }

    public function lastPurchase(): HasOne
    {
        return $this->hasOne(InventoryMovement::class)
            ->ofMany(
                ['id' => 'max'],
                fn ($query) => $query->where('type', 'in')->whereNotNull('cost_price')
            );
    }
}
