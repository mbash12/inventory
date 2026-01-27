<?php

namespace Src\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingVendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
    ];

    protected $casts = [
        'id' => 'integer',
    ];
    
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
