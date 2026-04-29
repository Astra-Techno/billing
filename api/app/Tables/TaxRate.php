<?php

namespace App\Tables;

use App\Base\Table;

class TaxRate extends Table
{
    protected string $table      = 'tax_rates';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'name', 'rate',
        'cgst_rate', 'sgst_rate', 'igst_rate', 'utgst_rate',
        'is_default', 'active',
    ];
    protected array $guarded = ['id'];
}
