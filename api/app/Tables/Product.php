<?php

namespace App\Tables;

use App\Base\Table;

class Product extends Table
{
    protected string $table      = 'products';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'type', 'name', 'description',
        'hsn_sac', 'unit', 'price', 'tax_rate_id', 'sku', 'active',
    ];
    protected array $guarded = ['id'];
}
