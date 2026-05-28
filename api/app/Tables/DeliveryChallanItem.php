<?php

namespace App\Tables;

use App\Base\Table;

class DeliveryChallanItem extends Table
{
    protected string $table      = 'delivery_challan_items';
    protected string $primaryKey = 'id';
    protected bool   $timestamps = false;
    protected array  $fillable   = [
        'delivery_challan_id', 'product_id', 'description',
        'hsn_sac', 'unit', 'quantity', 'sort_order',
    ];
    protected array $guarded = ['id'];
}
