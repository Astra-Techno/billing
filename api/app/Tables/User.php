<?php

namespace App\Tables;

use App\Base\Table;

class User extends Table
{
    protected string $table      = 'users';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'name', 'email', 'password', 'role', 'active',
        'phone', 'company', 'avatar',
    ];

    protected array $guarded = ['id'];

    protected bool $timestamps    = true;
    protected bool $softDelete    = false;
}
