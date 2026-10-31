<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransfer extends Model
{
    use Auditable;

    protected $fillable = [
        'source_batch_id',
        'destination_batch_id',
        'product_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'quantity',
        'transfer_date',
        'created_by',
        'vehicle_number',
        'driver_name',
        'receiver_name',
        'authorized_by_name',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'transfer_date' => 'date',
    ];

    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'source_batch_id');
    }

    public function destinationBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'destination_batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
