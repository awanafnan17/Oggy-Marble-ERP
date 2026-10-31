<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityInspection extends Model
{
    use Auditable;

    protected $table = 'quality_inspections';

    protected $fillable = [
        'qc_number',
        'inspection_date',
        'product_id',
        'inventory_batch_id',
        'production_schedule_id',
        'inspection_type',
        'grade',
        'defect_type',
        'checked_quantity',
        'rejected_quantity',
        'accepted_quantity',
        'inspector_name',
        'remarks',
        'status',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'checked_quantity' => 'decimal:4',
        'rejected_quantity' => 'decimal:4',
        'accepted_quantity' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function productionSchedule(): BelongsTo
    {
        return $this->belongsTo(ProductionSchedule::class);
    }
}
