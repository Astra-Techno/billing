<?php

namespace App\Tables;

use App\Base\Table;

class QuoteItem extends Table
{
    protected string $table      = 'quote_items';
    protected string $primaryKey = 'id';
    protected bool   $timestamps = false;
    protected array  $fillable   = [
        'quote_id', 'product_id', 'description', 'hsn_sac', 'unit',
        'quantity', 'unit_price', 'discount_pct', 'discount_amt', 'taxable_amt',
        'gst_rate', 'cgst_rate', 'sgst_rate', 'igst_rate',
        'cgst_amt', 'sgst_amt', 'igst_amt',
        'total', 'sort_order',
    ];
    protected array $guarded = ['id'];
}
