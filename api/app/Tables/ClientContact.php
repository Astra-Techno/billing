<?php

namespace App\Tables;

use App\Base\Table;

class ClientContact extends Table
{
    protected string $table      = 'client_contacts';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'client_id', 'business_id', 'name', 'designation',
        'email', 'mobile', 'phone', 'whatsapp',
        'is_primary', 'send_invoice', 'notes', 'active',
    ];
    protected array $guarded = ['id'];
}
