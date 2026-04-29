<?php

namespace App\Tables;

use App\Base\Table;

class Invoice extends Table
{
    protected string $table      = 'invoices';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'created_by', 'client_id', 'quote_id',
        'number', 'invoice_type', 'status',
        'issue_date', 'due_date', 'financial_year',
        'supply_type', 'place_of_supply', 'reverse_charge',
        'subtotal', 'cgst_total', 'sgst_total', 'igst_total', 'utgst_total',
        'tax_total', 'discount', 'round_off', 'total', 'amount_paid', 'amount_due',
        'is_recurring', 'recur_every', 'recur_period', 'recur_ends_at', 'next_recur_date',
        'irn', 'irn_generated_at', 'qr_code', 'ack_no', 'ack_date',
        'ewaybill_no', 'ewaybill_date',
        'notes', 'terms', 'sent_at', 'paid_at',
    ];
    protected array $guarded = ['id'];
}
