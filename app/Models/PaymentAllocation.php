<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentAllocation extends Model
{
    protected $fillable = [
        'payment_id',
        'allocatable_type',
        'allocatable_id',
        'amount',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'voided_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function allocatable(): MorphTo
    {
        return $this->morphTo();
    }
}
