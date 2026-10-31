<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrder extends Model
{
    use Auditable;

    protected $fillable = [
        'bom_id',
        'status',
        'quantity',
        'actual_material_cost',
        'actual_labor_cost',
        'actual_overhead_cost',
        'start_date',
        'completed_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'actual_material_cost' => 'decimal:4',
        'actual_labor_cost' => 'decimal:4',
        'actual_overhead_cost' => 'decimal:4',
        'start_date' => 'date',
        'completed_date' => 'date',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }
}
