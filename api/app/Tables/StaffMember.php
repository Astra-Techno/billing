<?php

namespace App\Tables;

use App\Base\Table;

class StaffMember extends Table
{
    protected string $table      = 'staff_members';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'business_id', 'name', 'role', 'mobile', 'email',
        'monthly_salary', 'join_date', 'notes', 'is_active',
    ];
    protected array $guarded = ['id'];
}
