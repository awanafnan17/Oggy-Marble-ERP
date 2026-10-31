<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bom extends Model
{
    protected $fillable = ['product_id', 'name', 'labor_cost_estimate', 'overhead_cost_estimate'];

    protected $casts = [
        'labor_cost_estimate' => 'decimal:4',
        'overhead_cost_estimate' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class); // finished product
    }

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class); // raw materials
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }
}
