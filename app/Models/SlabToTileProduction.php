<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlabToTileProduction extends Model
{
    protected $table = 'slab_to_tile_productions';

    protected $fillable = [
        'production_order_number',
        'input_batch_id',
        'input_quantity',
        'tile_product_id',
        'tiles_produced',
        'wastage_percentage',
        'scrap_product_id',
        'scrap_quantity',
        'scrap_unit',
        'labor_cost',
        'cutting_cost',
        'polishing_cost',
        'packaging_cost',
        'overhead_cost',
        'total_cost',
        'cost_per_sqft',
        'conversion_ratio',
        'production_date',
        'journal_entry_id',
        'expected_material_cost',
        'expected_labor_cost',
        'expected_overhead_cost',
        'expected_total_cost',
        'variance_amount',
        'variance_percentage',
        'is_favorable',
        'variance_journal_entry_id',
        'production_schedule_id',
        'wastage_quantity',
        'waste_reason',
        'waste_operator_name',
    ];

    protected $casts = [
        'input_quantity' => 'decimal:4',
        'tiles_produced' => 'decimal:4',
        'wastage_percentage' => 'decimal:4',
        'scrap_quantity' => 'decimal:4',
        'labor_cost' => 'decimal:4',
        'cutting_cost' => 'decimal:4',
        'polishing_cost' => 'decimal:4',
        'packaging_cost' => 'decimal:4',
        'overhead_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'cost_per_sqft' => 'decimal:4',
        'conversion_ratio' => 'decimal:4',
        'expected_material_cost' => 'decimal:4',
        'expected_labor_cost' => 'decimal:4',
        'expected_overhead_cost' => 'decimal:4',
        'expected_total_cost' => 'decimal:4',
        'variance_amount' => 'decimal:4',
        'variance_percentage' => 'decimal:4',
        'is_favorable' => 'boolean',
        'production_date' => 'date',
        'production_schedule_id' => 'integer',
        'wastage_quantity' => 'decimal:4',
    ];

    public function inputBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'input_batch_id');
    }

    public function tileProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'tile_product_id');
    }

    public function scrapProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'scrap_product_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function varianceJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'variance_journal_entry_id');
    }

    public function productionSchedule(): BelongsTo
    {
        return $this->belongsTo(ProductionSchedule::class, 'production_schedule_id');
    }
}
