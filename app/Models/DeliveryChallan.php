<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryChallan extends Model
{
    use Auditable;

    protected $table = 'delivery_challans';

    protected $fillable = [
        'challan_number',
        'challan_date',
        'customer_invoice_id',
        'order_id',
        'vehicle_number',
        'driver_name',
        'receiver_name',
        'prepared_by',
        'authorized_by',
        'remarks',
        'status',
        'dispatched_at',
        'delivered_at',
    ];

    protected $casts = [
        'challan_date' => 'datetime',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function customerInvoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryChallanItem::class);
    }
}
