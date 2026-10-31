<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PakistanBank extends Model
{
    protected $fillable = [
        'name',
        'short_name',
        'category',
        'is_islamic',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_islamic' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Scope: only active banks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get banks grouped by category for dropdown rendering.
     */
    public static function getGroupedForDropdown(): array
    {
        return static::active()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('category')
            ->map(function ($banks, $category) {
                return [
                    'label' => static::categoryLabel($category),
                    'banks' => $banks->map(fn ($b) => [
                        'id' => $b->id,
                        'name' => $b->name,
                        'short_name' => $b->short_name,
                        'is_islamic' => $b->is_islamic,
                    ])->values()->toArray(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Human-readable category label.
     */
    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            'commercial' => 'Commercial / Scheduled Banks',
            'islamic' => 'Islamic Banks / Islamic Banking',
            'digital' => 'Digital / Branchless / Wallet Banks',
            'microfinance' => 'Microfinance Banks',
            'specialized' => 'Specialized / Cooperative Banks',
            'foreign' => 'Foreign / International Banks',
            default => ucfirst($category),
        };
    }
}
