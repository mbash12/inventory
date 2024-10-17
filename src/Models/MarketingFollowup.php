<?php
 
namespace Src\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Models\User;

class MarketingFollowup extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'po_deposit',
        'schedule_date',
        'followup_date',
        'note',
    ];
    
    protected $casts = [
        'id' => 'integer',
        'po_deposit' => 'integer'
    ];
    

}