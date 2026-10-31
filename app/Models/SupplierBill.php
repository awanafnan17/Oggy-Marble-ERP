<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SupplierBill extends Model
{
    use Auditable;

    protected $fillable = [
        'order_id',
        'contact_id',
        'bill_number',
        'issue_date',
        'due_date',
        'total_amount',
        'remaining_balance',
        'status',
        'gross_amount',
        'discount_amount',
        'delivery_charges',
        'net_amount',
        'cash_paid',
        'bank_paid',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:4',
        'remaining_balance' => 'decimal:4',
        'gross_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'delivery_charges' => 'decimal:4',
        'net_amount' => 'decimal:4',
        'cash_paid' => 'decimal:4',
        'bank_paid' => 'decimal:4',
        'issue_date' => 'date',
        'due_date' => 'date',
        'voided_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function allocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }
}
