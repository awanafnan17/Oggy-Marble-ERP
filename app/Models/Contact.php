<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = [
        'name',
        'type',
        'email',
        'phone',
        'address',
        'opening_balance',
        'opening_balance_type',
        'status',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
