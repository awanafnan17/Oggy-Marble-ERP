<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriod extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'period_name',
        'start_date',
        'end_date',
        'is_closed',
        'closed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_closed' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * The user who closed this period.
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
