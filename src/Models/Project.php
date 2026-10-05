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
        'stock_in_required',
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
        'stock_in_required' => 'boolean',
        'client_po_date' => 'date',
        'is_po_deposit' => 'boolean',
        'planned_action' => 'boolean',
        'total_price' => 'decimal:2',
        'invoiced_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'used_amount' => 'decimal:2',
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

    /**
     * Get the parent deposit project (for actual projects)
     */
    public function deposit_project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'deposit_id');
    }

    /**
     * Get child actual projects (for deposit projects)
     */
    public function actual_projects(): HasMany
    {
        return $this->hasMany(Project::class, 'deposit_id');
    }
}
