<?php

namespace Src\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingSyncSnapshot extends Model
{
    protected $fillable = [
        'po_deposit_id',
        'so_key',
        'company_id',
        'sync_version',
        'accounting_so_id',
        'accounting_order_number',
        'state',
        'synced_at',
    ];

    protected $casts = [
        'po_deposit_id' => 'integer',
        'company_id' => 'integer',
        'sync_version' => 'integer',
        'accounting_so_id' => 'integer',
        'state' => 'array',
        'synced_at' => 'datetime',
    ];
}
