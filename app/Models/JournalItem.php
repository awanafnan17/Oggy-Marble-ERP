<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalItem extends Model
{
    protected $fillable = ['journal_entry_id', 'account_id', 'debit', 'credit'];

    protected $casts = [
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected static function booted()
    {
        static::updating(function ($model) {
            throw new \Exception('Journal items are immutable and cannot be updated.');
        });

        static::deleting(function ($model) {
            throw new \Exception('Journal items are immutable and cannot be deleted.');
        });
    }
}
