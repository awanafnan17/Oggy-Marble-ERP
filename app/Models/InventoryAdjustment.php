<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustment extends Model
{
    use Auditable;

    protected $fillable = [
        'inventory_batch_id',
        'product_id',
        'warehouse_id',
        'system_quantity',
        'physical_quantity',
        'difference',
        'reason',
        'adjustment_date',
        'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'system_quantity' => 'decimal:4',
        'physical_quantity' => 'decimal:4',
        'difference' => 'decimal:4',
        'adjustment_date' => 'date',
    ];

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
