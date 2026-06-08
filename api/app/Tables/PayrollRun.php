<?php

namespace App\Tables;

use App\Base\Table;

class PayrollRun extends Table
{
    protected string $table      = 'payroll_runs';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'staff_id', 'month', 'year',
        'basic_salary', 'days_worked', 'working_days',
        'bonus', 'deductions', 'net_pay',
        'status', 'paid_date', 'method', 'expense_id', 'note',
    ];
    protected array $guarded = ['id'];
}
