<?php

namespace App\Tables;

use App\Base\Table;

class ActivityLog extends Table
{
    protected string $table      = 'activity_logs';
    protected string $primaryKey = 'id';
    protected bool   $timestamps = false;
    protected array  $fillable   = [
        'business_id', 'user_id', 'action', 'model_type', 'model_id',
        'description', 'ip_address',
    ];
    protected array $guarded = ['id'];
}
