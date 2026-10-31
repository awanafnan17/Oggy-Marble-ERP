<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryChallanItem extends Model
{
    protected $table = 'delivery_challan_items';

    protected $fillable = [
        'delivery_challan_id',
        'product_id',
        'inventory_batch_id',
        'quantity',
        'unit',
        'warehouse_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public function deliveryChallan(): BelongsTo
    {
        return $this->belongsTo(DeliveryChallan::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
