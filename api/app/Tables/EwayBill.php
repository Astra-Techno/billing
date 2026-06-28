<?php

namespace App\Tables;

use App\Base\Table;

class EwayBill extends Table
{
    protected string $table      = 'eway_bills';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'created_by', 'invoice_id',
        'ewb_number', 'status', 'mode', 'distance',
        'vehicle_no', 'vehicle_type', 'transporter',
        'valid_from', 'valid_until',
    ];
    protected array $guarded = ['id'];
}
