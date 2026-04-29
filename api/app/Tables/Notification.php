<?php

namespace App\Tables;

use App\Base\Table;

class Notification extends Table
{
    protected string $table      = 'notifications';
    protected string $primaryKey = 'id';
    protected bool   $timestamps = false;
    protected string $createdColumn = 'created_at';
    protected array  $fillable   = [
        'business_id', 'user_id', 'type', 'title', 'message', 'data', 'read_at',
    ];
    protected array $guarded = ['id'];
}
