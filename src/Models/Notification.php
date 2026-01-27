<?php
 
namespace Src\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Models\User;

class Notification extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'title',
        'content',
        'readed_at',
        'user',
        'payload',
        'position',
        'type',
    ];
    
    protected $casts = [
        'id' => 'integer',
        'payload' => 'json'
    ];
    
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}