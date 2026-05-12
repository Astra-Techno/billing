<?php

namespace App\Tables;

use App\Base\Table;

class PurchaseOrder extends Table
{
    protected string $table      = 'purchase_orders';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'created_by', 'supplier_id',
        'number', 'status',
        'order_date', 'expected_date',
        'subtotal', 'tax_total', 'total',
        'notes',
    ];
    protected array $guarded = ['id'];
}
