<?php

namespace App\Tables;

use App\Base\Table;

class PurchaseReturnItem extends Table
{
    protected string $table      = 'purchase_return_items';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'pr_id', 'product_id', 'description', 'hsn_sac', 'unit',
        'quantity', 'unit_price', 'taxable_amt', 'gst_rate',
        'cgst_amt', 'sgst_amt', 'igst_amt', 'total',
        'batch_no', 'expiry_date', 'sort_order',
    ];
    protected array $guarded = ['id'];
}
