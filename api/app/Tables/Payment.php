<?php

namespace App\Tables;

use App\Base\Table;

class Payment extends Table
{
    protected string $table      = 'payments';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'invoice_id', 'client_id', 'recorded_by',
        'amount', 'method', 'reference', 'utr_number', 'payment_date', 'note',
    ];
    protected array $guarded = ['id'];
}
