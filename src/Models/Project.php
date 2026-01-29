<?php

namespace Src\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Models\Product;
use Src\Models\ShippingVendor;
use Src\Models\Inventory;
use Src\Models\PoDeposit;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'job_number',
        'client_po_date',
        'client_po_number',
        'client_pic_name',
        'status',
        'shipping_vendor',
        'manufacture',
        'pic_name',
        'client_company',
        'client_code',
        'po_deposit',
        'is_po_deposit',
        'deposit',
        'is_real',
        'sent_to_del_at',
        'total_price',
        'production_deadline',
        'delivery_deadline',
        'po_deadline',
        'invoiced_amount',
        'remaining_amount',
        'used_amount',
        'deposit_id',
        'deadline_meta',
        'invoices',
        'invoice_status',
        'invoice_pic',
        'project_type',
        'bast_files',
        'gr_files',
        'do_files',
        'documents',
        'do_deadline',
        'bast_deadline',
        'design_deadline',
        'gr_deadline',
    ];

    protected $casts = [
        'id' => 'integer',
        'client_po_date' => 'date',
        'is_po_deposit' => 'boolean',
        'planned_action' => 'boolean',
    ];

    public function products_data(): HasMany
    {
        return $this->hasMany(Product::class, 'project');
    }
    public function inventories_data(): HasMany
    {
        return $this->hasMany(Inventory::class, 'project');
    }
    public function shipping_vendors_data(): BelongsTo
    {
        return $this->belongsTo(ShippingVendor::class, 'shipping_vendor');
    }
    public function manufacture_data(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'manufacture');
    }
    public function po_deposit_data(): BelongsTo
    {
        return $this->belongsTo(PoDeposit::class, 'po_deposit');
    }
    public function deliveries_data(): HasMany
    {
        return $this->hasMany(Delivery::class, 'project');
    }
}
