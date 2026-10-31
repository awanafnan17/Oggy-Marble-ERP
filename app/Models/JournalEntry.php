<?php

namespace App\Models;

use App\Traits\Auditable;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use Auditable;

    protected $fillable = [
        'entry_number',
        'date',
        'description',
        'reference_type',
        'reference_id',
        'reversal_journal_entry_id',
        'reversed_by_entry_id',
        'original_reference_type',
        'original_reference_id',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'voided_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(JournalItem::class);
    }

    public function reversalJournalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }

    public function reversedByEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_by_entry_id');
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    protected static function booted()
    {
        static::updating(function ($model) {
            $dirty = $model->getDirty();
            $allowed = [
                'reference_type',
                'reference_id',
                'reversal_journal_entry_id',
                'reversed_by_entry_id',
                'original_reference_type',
                'original_reference_id',
                'voided_at',
                'voided_by',
                'void_reason',
            ];
            foreach (array_keys($dirty) as $key) {
                if (! in_array($key, $allowed)) {
                    throw new Exception('Posted journal entries are immutable and cannot be updated.');
                }
            }
        });

        static::deleting(function ($model) {
            throw new Exception('Journal entries cannot be deleted.');
        });
    }
}
