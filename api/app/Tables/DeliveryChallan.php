<?php

namespace App\Tables;

use App\Base\Table;

class DeliveryChallan extends Table
{
    protected string $table      = 'delivery_challans';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'created_by', 'client_id',
        'number', 'status', 'issue_date', 'delivery_date',
        'vehicle_no', 'driver_name', 'destination', 'notes',
    ];
    protected array $guarded = ['id'];
}
