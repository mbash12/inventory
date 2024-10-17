<?php
 
namespace Src\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'name',
    ];
    
    protected $casts = [
        'id' => 'integer',
    ];
}