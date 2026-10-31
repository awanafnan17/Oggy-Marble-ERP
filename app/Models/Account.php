<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = ['code', 'name', 'type'];

    public function journalItems(): HasMany
    {
        return $this->hasMany(JournalItem::class);
    }
}
