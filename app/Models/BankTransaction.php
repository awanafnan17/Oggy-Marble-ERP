<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    use Auditable;

    protected $fillable = [
        'bank_account_id',
        'date',
        'type',
        'amount',
        'reference_type',
        'reference_id',
        'description',
        'proof_image_path',
        'proof_image_size',
        'proof_image_mime',
        'is_reconciled',
        'bank_reconciliation_id',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'date' => 'date',
        'is_reconciled' => 'boolean',
        'voided_at' => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function bankReconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
