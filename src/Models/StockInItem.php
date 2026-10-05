<?php

namespace Src\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockInItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'stock_in',
        'product',
        'manual_item',
        'quantity',
        'actual_quantity',
        'received_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'stock_in' => 'integer',
        'product' => 'integer',
        'manual_item' => 'integer',
        'quantity' => 'integer',
        'actual_quantity' => 'integer',
        'received_at' => 'datetime',
    ];

    public function stock_in_data(): BelongsTo
    {
        return $this->belongsTo(StockIn::class, 'stock_in');
    }

    public function product_data(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product');
    }

    public function manual_item_data(): BelongsTo
    {
        return $this->belongsTo(ManualItem::class, 'manual_item');
    }
}
