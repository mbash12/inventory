<?php

namespace Src\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualInventory extends Model
{
    use HasFactory;

    protected $fillable = ['manual_item', 'warehouse', 'quantity'];

    protected $casts = [
        'id' => 'integer',
        'manual_item' => 'integer',
        'warehouse' => 'integer',
        'quantity' => 'integer',
    ];

    public function manual_item_data(): BelongsTo
    {
        return $this->belongsTo(ManualItem::class, 'manual_item');
    }

    public function warehouse_data(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse');
    }
}
