<?php

namespace App\Tables;

use App\Base\Table;

class Quote extends Table
{
    protected string $table      = 'quotes';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'created_by', 'client_id',
        'number', 'type', 'status',
        'issue_date', 'valid_until', 'financial_year',
        'supply_type', 'place_of_supply',
        'subtotal', 'cgst_total', 'sgst_total', 'igst_total', 'utgst_total',
        'tax_total', 'discount', 'total',
        'notes', 'terms', 'sent_at', 'accepted_at', 'converted_at',
    ];
    protected array $guarded = ['id'];
}
