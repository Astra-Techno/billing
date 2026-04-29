<?php

namespace App\Tables;

use App\Base\Table;

class Setting extends Table
{
    protected string $table      = 'settings';
    protected string $primaryKey = 'id';
    protected array  $fillable   = ['business_id', 'key', 'value'];
    protected array  $guarded    = ['id'];
}
