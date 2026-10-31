<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionSchedule extends Model
{
    use Auditable;

    protected $table = 'production_schedules';

    protected $fillable = [
        'schedule_number',
        'production_order_reference',
        'production_type',
        'machine',
        'planned_start_date',
        'planned_end_date',
        'priority',
        'planned_input_product_id',
        'planned_input_batch_id',
        'planned_input_quantity',
        'expected_output_quantity',
        'expected_wastage_quantity',
        'expected_wastage_percentage',
        'assigned_operator',
        'status',
        'remarks',
    ];

    protected $casts = [
        'planned_start_date' => 'datetime',
        'planned_end_date' => 'datetime',
        'planned_input_quantity' => 'decimal:4',
        'expected_output_quantity' => 'decimal:4',
        'expected_wastage_quantity' => 'decimal:4',
        'expected_wastage_percentage' => 'decimal:4',
    ];

    public function plannedInputProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'planned_input_product_id');
    }

    public function plannedInputBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'planned_input_batch_id');
    }

    public function stoneToSlabProductions(): HasMany
    {
        return $this->hasMany(StoneToSlabProduction::class, 'production_schedule_id');
    }

    public function slabToTileProductions(): HasMany
    {
        return $this->hasMany(SlabToTileProduction::class, 'production_schedule_id');
    }
}
