<?php

namespace App\Tables;

use App\Base\Table;

class Client extends Table
{
    protected string $table      = 'clients';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'type', 'name', 'company', 'gstin', 'pan',
        'email', 'mobile', 'phone',
        'address_line1', 'address_line2', 'city', 'state_id', 'pincode',
        'currency', 'credit_limit', 'credit_days',
        'portal_token', 'notes', 'active',
    ];
    protected array $guarded = ['id'];
}
