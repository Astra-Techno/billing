<?php

namespace App\Tables;

use App\Base\Table;

class PurchaseReturn extends Table
{
    protected string $table      = 'purchase_returns';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'created_by', 'pi_id', 'supplier_id', 'location_id',
        'number', 'reason', 'return_date',
        'supply_type', 'place_of_supply',
        'subtotal', 'cgst_total', 'sgst_total', 'igst_total', 'tax_total', 'total',
        'status', 'notes',
    ];
    protected array $guarded = ['id'];
}
