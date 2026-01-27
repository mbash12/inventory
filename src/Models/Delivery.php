<?php

namespace Src\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Models\DeliveryItem;
use Src\Models\Project;
use Src\Models\ShippingVendor;

class Delivery extends Model
{
    use HasFactory;


    protected $fillable = [
        'project',
        'delivery_date',
        'default_origin',
        'destination',
        'shipping_vendor',
        'do_number',
        'do_file',
        'do_files',
        'receipt_files',
        'status',
    ];


    protected $casts = [
        'id' => 'integer',
        'delivery_date' => 'date',
        'project' => 'integer',
        'default_origin' => 'integer',
        'destination' => 'integer',
        'shipping_vendor' => 'integer',
    ];

    public function delivery_items_data(): HasMany
    {
        return $this->hasMany(DeliveryItem::class, 'delivery');
    }

    public function project_data(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project');
    }
    public function default_origin_data(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_origin');
    }
    public function destination_data(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination');
    }
    public function shipping_vendor_data(): BelongsTo
    {
        return $this->belongsTo(ShippingVendor::class, 'shipping_vendor');
    }
}
