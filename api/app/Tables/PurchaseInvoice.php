<?php

namespace App\Tables;

use App\Base\Table;

class PurchaseInvoice extends Table
{
    protected string $table      = 'purchase_invoices';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'created_by', 'supplier_id', 'po_id', 'location_id',
        'number', 'supplier_inv_no', 'status',
        'invoice_date', 'due_date', 'financial_year',
        'supply_type', 'place_of_supply', 'reverse_charge',
        'subtotal', 'cgst_total', 'sgst_total', 'igst_total', 'tax_total',
        'discount', 'round_off', 'total', 'amount_paid', 'amount_due',
        'notes',
    ];
    protected array $guarded = ['id'];
}
