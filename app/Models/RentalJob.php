<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RentalJob extends Model
{
    protected $fillable = [
        'contact_id',
        'job_date',
        'job_number',
        'machine_hours',
        'machine_rate',
        'labor_hours',
        'labor_rate',
        'other_charges',
        'total_amount',
        'cash_received',
        'bank_received',
        'remaining_receivable',
        'payment_status',
        'notes',
        'journal_entry_id',
    ];

    protected $casts = [
        'job_date' => 'date',
        'machine_hours' => 'decimal:2',
        'machine_rate' => 'decimal:4',
        'labor_hours' => 'decimal:2',
        'labor_rate' => 'decimal:4',
        'other_charges' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'cash_received' => 'decimal:4',
        'bank_received' => 'decimal:4',
        'remaining_receivable' => 'decimal:4',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function allocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }
}
