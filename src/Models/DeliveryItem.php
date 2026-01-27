<?php

namespace Src\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Models\Warehouse;
use Src\Models\Delivery;
use Src\Models\Product;

class DeliveryItem extends Model
{
    use HasFactory;


    protected $fillable = [
        'delivery',
        'origin',
        'destination',
        'product',
        'quantity',
        'actual_quantity',
        'delivered_at'
    ];


    protected $casts = [
        'id' => 'integer',
        'origin' => 'integer',
        'destination' => 'integer',
        'delivery' => 'integer',
        'product' => 'integer',
        'delivered_at' => 'datetime',
    ];

    public function destination_data(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination');
    }
    public function origin_data(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'origin');
    }
    public function product_data(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product');
    }

    public function delivery_data(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery');
    }
}
