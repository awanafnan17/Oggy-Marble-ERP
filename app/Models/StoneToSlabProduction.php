<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoneToSlabProduction extends Model
{
    protected $table = 'stone_to_slab_productions';

    protected $fillable = [
        'production_order_number',
        'input_batch_id',
        'input_quantity',
        'slab_product_id',
        'slabs_produced',
        'wastage_quantity',
        'scrap_product_id',
        'scrap_quantity',
        'scrap_unit',
        'labor_cost',
        'electricity_cost',
        'fuel_cost',
        'maintenance_cost',
        'overhead_cost',
        'total_cost',
        'cost_per_mt',
        'yield_percentage',
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
        'wastage_percentage',
        'waste_reason',
        'waste_operator_name',
    ];

    protected $casts = [
        'input_quantity' => 'decimal:4',
        'slabs_produced' => 'decimal:4',
        'wastage_quantity' => 'decimal:4',
        'scrap_quantity' => 'decimal:4',
        'labor_cost' => 'decimal:4',
        'electricity_cost' => 'decimal:4',
        'fuel_cost' => 'decimal:4',
        'maintenance_cost' => 'decimal:4',
        'overhead_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'cost_per_mt' => 'decimal:4',
        'yield_percentage' => 'decimal:4',
        'expected_material_cost' => 'decimal:4',
        'expected_labor_cost' => 'decimal:4',
        'expected_overhead_cost' => 'decimal:4',
        'expected_total_cost' => 'decimal:4',
        'variance_amount' => 'decimal:4',
        'variance_percentage' => 'decimal:4',
        'is_favorable' => 'boolean',
        'production_date' => 'date',
        'production_schedule_id' => 'integer',
        'wastage_percentage' => 'decimal:4',
    ];

    public function inputBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'input_batch_id');
    }

    public function slabProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'slab_product_id');
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
