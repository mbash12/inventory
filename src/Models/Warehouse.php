<?php
 
namespace Src\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'name',
        'storage'
    ];
    
    protected $casts = [
        'id' => 'integer',
        'storage' => 'boolean',
    ];
}