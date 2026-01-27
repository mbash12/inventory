<?php
 
namespace Src\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Thread extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'type',
        'user_id',
        'project_id',
        'notes',
        'meta_data',
        'reminder',
        'deadline',
        'files'
    ];
    
    protected $casts = [
        'id' => 'integer',
    ];
}