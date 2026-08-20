<?php

namespace Src\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Models\Project;

class Product extends Model
{
    use HasFactory;


    protected $fillable = [
        'project',
        'product_code',
        'name',
        'description',
        'quantity',
        'qty_per_set',
        'is_group_main',
        'price',
        'total_price',
        'is_production',
        'date',
        'design_files',
        'design_approved',
        'uom_code',
        'tax_code',
        'include_ppn'
    ];

    protected $casts = [
        'id' => 'integer',
        'project' => 'integer',
        'is_production' => 'boolean',
        'include_ppn' => 'boolean',
        'is_group_main' => 'boolean',
        'uom_code' => 'string',
        'tax_code' => 'string',
        'price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'qty_per_set' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
    public function delivery_items_data(): HasMany
    {
        return $this->hasMany(DeliveryItem::class, "product")->with('delivery_data');
    }
}
