<?php

namespace App\Tables;

use App\Base\Table;

class BusinessUser extends Table
{
    protected string $table      = 'business_users';
    protected string $primaryKey = 'id';
    protected bool   $timestamps = true;
    protected array  $fillable   = [
        'business_id', 'user_id', 'role', 'invited_by', 'accepted_at', 'active',
    ];
    protected array $guarded = ['id'];
}
