<?php

namespace App\Tables;

use App\Base\Table;

class DebitNote extends Table
{
    protected string $table      = 'debit_notes';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'created_by', 'invoice_id', 'client_id',
        'number', 'reason', 'issue_date',
        'supply_type', 'place_of_supply',
        'subtotal', 'cgst_total', 'sgst_total', 'igst_total', 'tax_total', 'total',
        'status', 'notes',
    ];
    protected array $guarded = ['id'];
}
