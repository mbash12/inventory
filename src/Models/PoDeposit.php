<?php
 
namespace Src\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Models\Project;



class PoDeposit extends Model
{
    // use HasFactory, SoftDeletes;
    use HasFactory;

    // protected $table = 'po_deposit';

    protected $fillable = [
        'job_number',
        'client_po_date',
        'client_po_number',
        'client_company',
        'client_pic_name',
        'pic_name',
        'status',
        'purchase_ordres',
        'invoices',
        'is_po_deposit',
        'closed_at',
        'budget',
        'expense',
        'balance',
        'title',
        'total_price'
    ];
    
    protected $casts = [
        'id' => 'integer',
        'client_po_date' => 'date',
        'closed_at' => 'date',
    ];
    


    public function invoice_progresses_data(): HasMany
    {
        return $this->hasMany(InvoiceProgress::class, 'po_deposit');
    }
    public function marketing_followups(): HasMany
    {
        return $this->hasMany(MarketingFollowup::class, 'po_deposit');
    }
    public function projects_data(): HasMany
    {
        return $this->hasMany(Project::class, 'po_deposit');
    }
}