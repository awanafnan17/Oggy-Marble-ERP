<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = ['name', 'location'];

    public function stockLedgers(): HasMany
    {
        return $this->hasMany(StockLedger::class);
    }
}
