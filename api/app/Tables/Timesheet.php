<?php

namespace App\Tables;

use App\Base\Table;

class Timesheet extends Table
{
    protected string $table      = 'timesheets';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'user_id', 'work_date', 'hours', 'description',
        'project', 'status', 'approved_by', 'approved_at',
    ];
    protected array $guarded = ['id'];
}
