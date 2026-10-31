<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use Auditable;

    protected $fillable = [
        'payment_number',
        'contact_id',
        'payment_date',
        'amount',
        'type',
        'payment_method',
        'notes',
        'bank_account_id',
        'status',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'payment_date' => 'date',
        'voided_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
