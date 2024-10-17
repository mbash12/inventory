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
        'name',
        'description',
        'quantity',
        'price',
        'total_price',
        'is_production',
        'date',
    ];

    protected $casts = [
        'id' => 'integer',
        'project' => 'integer',
        'is_production' => 'boolean'
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
    public function delivery_items_data(): HasMay
    {
        return $this->hasMany(DeliveryItem::class, "product");
    }
}
