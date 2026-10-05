<?php

namespace Src\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockIn extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'direction',
        'project',
        'do_number',
        'document_date',
        'origin',
        'warehouse',
        'files',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'id' => 'integer',
        'project' => 'integer',
        'origin' => 'integer',
        'warehouse' => 'integer',
        'document_date' => 'date:Y-m-d',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockInItem::class, 'stock_in');
    }

    public function project_data(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project');
    }

    public function warehouse_data(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse');
    }

    public function origin_data(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'origin');
    }
}
