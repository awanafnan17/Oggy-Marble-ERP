<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBatch extends Model
{
    use Auditable;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'reference_type',
        'reference_id',
        'quantity_received',
        'quantity_remaining',
        'unit_cost',
        'received_at',
        'unit_of_measure',
        'product_type',
        'batch_reference',
        'total_cost',
        'quality_grade',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:4',
        'quantity_remaining' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'received_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockLedgers(): HasMany
    {
        return $this->hasMany(StockLedger::class, 'batch_id');
    }
}
