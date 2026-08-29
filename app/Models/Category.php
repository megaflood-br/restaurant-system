<?php

namespace App\Models;

use App\Support\WeeklyMenuImages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'available_days',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'available_days' => 'array',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * null/[] = todos os dias. Caso contrário, só os dias listados (monday…sunday).
     */
    public function isAvailableOnDay(?string $day = null): bool
    {
        $day = $day ?? WeeklyMenuImages::todayKey();
        $days = $this->normalizedAvailableDays();

        if ($days === []) {
            return true;
        }

        return in_array($day, $days, true);
    }

    /** @return list<string> */
    public function normalizedAvailableDays(): array
    {
        $days = $this->available_days;

        if (! is_array($days) || $days === []) {
            return [];
        }

        $allowed = WeeklyMenuImages::DAYS;

        return array_values(array_filter(
            array_map('strval', $days),
            fn (string $day) => in_array($day, $allowed, true)
        ));
    }

    public function availableDaysLabel(): string
    {
        $days = $this->normalizedAvailableDays();

        if ($days === []) {
            return 'Todos os dias';
        }

        $labels = WeeklyMenuImages::labels();

        return collect($days)
            ->map(fn (string $day) => $labels[$day] ?? $day)
            ->implode(', ');
    }

    public function scopeAvailableOnDay(Builder $query, ?string $day = null): Builder
    {
        $day = $day ?? WeeklyMenuImages::todayKey();

        return $query->where(function (Builder $inner) use ($day) {
            $inner->whereNull('available_days')
                ->orWhereJsonLength('available_days', 0)
                ->orWhereJsonContains('available_days', $day);
        });
    }

    /** @param  list<string>|null  $days */
    public static function normalizeDaysInput(?array $days): ?array
    {
        if ($days === null || $days === []) {
            return null;
        }

        $normalized = array_values(array_filter(
            array_map('strval', $days),
            fn (string $day) => in_array($day, WeeklyMenuImages::DAYS, true)
        ));

        return $normalized === [] ? null : $normalized;
    }
}
