<?php
 
namespace Src\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;


class InvoiceProgress extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'invoice_progresses';

    protected $fillable = [
        'po_deposit',
        'pic',
        'note',
        'status'
    ];
    
    protected $casts = [
        'id' => 'integer',
        'po_deposit' => 'integer'
    ];
    
}