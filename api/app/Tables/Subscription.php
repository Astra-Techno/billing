<?php

namespace App\Tables;

use App\Base\Table;

class Subscription extends Table
{
    protected string $table      = 'subscriptions';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'plan_id', 'status', 'billing_cycle',
        'trial_ends_at', 'current_period_start', 'current_period_end',
        'cancelled_at', 'ends_at', 'payment_method', 'external_id',
    ];
    protected array $guarded = ['id'];
}
