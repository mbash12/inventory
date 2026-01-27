<?php

namespace Src\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Models\Media;
use Src\Models\Project;
use Src\Models\Warehouse;
use Src\Models\Product;

class Inventory extends Model
{
    use HasFactory;


    protected $fillable = [
        'project',
        'product',
        'quantity',
        'direction',
        'warehouse',
        'storage',
        'warehouse_name',
        'product_name',
    ];

    protected $casts = [
        'id' => 'integer',
        'project' => 'integer',
        'warehouse' => 'integer',
        'product' => 'integer',
        'storage' => 'boolean',
    ];

    public function project_data(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project');
    }

    public function warehouse_data(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse');
    }

    public function product_data(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product');
    }
}
