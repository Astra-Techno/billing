<?php

namespace App\Tables;

use App\Base\Table;

class Sequence extends Table
{
    protected string $table      = 'sequences';
    protected string $primaryKey = 'id';
    protected bool   $timestamps = false;
    protected array  $fillable   = [
        'business_id', 'type', 'financial_year', 'prefix', 'next_number', 'padding',
    ];
    protected array $guarded = ['id'];
}
