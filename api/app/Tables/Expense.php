<?php

namespace App\Tables;

use App\Base\Table;

class Expense extends Table
{
    protected string $table      = 'expenses';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'category_id', 'recorded_by',
        'vendor_name', 'description', 'amount', 'gst_amount', 'total_amount',
        'expense_date', 'method', 'reference', 'receipt', 'financial_year', 'notes',
    ];
    protected array $guarded = ['id'];
}
