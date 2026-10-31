<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    use Auditable;

    protected $fillable = [
        'bank_account_id',
        'statement_date',
        'statement_balance',
        'system_balance',
        'difference',
        'is_closed',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'statement_balance' => 'decimal:4',
        'system_balance' => 'decimal:4',
        'difference' => 'decimal:4',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function bankTransactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
