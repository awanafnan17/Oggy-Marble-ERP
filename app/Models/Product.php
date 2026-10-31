<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use Auditable;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'unit',
        'cost',
        'price',
        'product_type',
        'unit_of_measure',
        'stone_color',
        'quality_grade',
        'thickness',
        'size',
        'inventory_account_id',
        'cogs_account_id',
        'revenue_account_id',
    ];

    protected $casts = [
        'cost' => 'decimal:4',
        'price' => 'decimal:4',
    ];

    public function stockLedgers(): HasMany
    {
        return $this->hasMany(StockLedger::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'inventory_account_id');
    }

    public function cogsAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cogs_account_id');
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }
}
