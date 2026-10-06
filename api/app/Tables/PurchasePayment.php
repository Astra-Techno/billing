<?php

namespace App\Tables;

use App\Base\Table;

class PurchasePayment extends Table
{
    protected string $table      = 'purchase_payments';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'pi_id', 'supplier_id', 'recorded_by',
        'amount', 'method', 'reference', 'utr_number', 'payment_date', 'note',
    ];
    protected array $guarded = ['id'];
}
