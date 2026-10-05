<?php

namespace Src\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManualItem extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'unit', 'active'];

    protected $casts = [
        'id' => 'integer',
        'active' => 'boolean',
    ];
}
