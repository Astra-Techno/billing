<?php

namespace App\Tables;

use App\Base\Table;

class PurchaseInvoiceItem extends Table
{
    protected string $table      = 'purchase_invoice_items';
    protected string $primaryKey = 'id';
    protected bool   $timestamps = false;
    protected array  $fillable   = [
        'pi_id', 'product_id', 'description', 'hsn_sac', 'unit',
        'quantity', 'unit_price', 'discount_pct', 'discount_amt', 'taxable_amt',
        'gst_rate', 'cgst_rate', 'sgst_rate', 'igst_rate',
        'cgst_amt', 'sgst_amt', 'igst_amt', 'total', 'sort_order',
        'batch_no', 'expiry_date',
    ];
    protected array $guarded = ['id'];
}
