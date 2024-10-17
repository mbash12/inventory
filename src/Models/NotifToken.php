<?php
 
namespace Src\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotifToken extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'token',
        'user_id'
    ];
    
    protected $casts = [
        'id' => 'integer',
    ];
}