<?php

namespace App\Tables;

use App\Base\Table;

class PurchaseOrderItem extends Table
{
    protected string $table      = 'purchase_order_items';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'po_id', 'product_id', 'description', 'hsn_sac', 'unit',
        'quantity', 'unit_price', 'gst_rate', 'total', 'sort_order',
    ];
    protected array $guarded = ['id'];
}
