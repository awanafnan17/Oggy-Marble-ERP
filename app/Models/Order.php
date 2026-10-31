<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'contact_id',
        'type',
        'order_number',
        'status',
        'total_amount',
        'tax_amount',
        'discount_amount',
        'order_date',
        'due_date',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'order_date' => 'date',
        'due_date' => 'date',
        'voided_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customerInvoice()
    {
        return $this->hasOne(CustomerInvoice::class);
    }

    public function supplierBill()
    {
        return $this->hasOne(SupplierBill::class);
    }
}
