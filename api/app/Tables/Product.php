<?php

namespace App\Tables;

use App\Base\Table;

class Product extends Table
{
    protected string $table      = 'products';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'type', 'name', 'description', 'pos_category', 'track_stock',
        'hsn_sac', 'unit', 'base_unit', 'conversion_factor', 'price', 'purchase_price', 'mrp',
        'reorder_level', 'tax_rate_id', 'sku', 'barcode', 'batch_tracking', 'expiry_tracking',
        'serial_tracking', 'active',
    ];
    protected array $guarded = ['id'];
}
