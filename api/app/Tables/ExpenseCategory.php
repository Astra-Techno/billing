<?php

namespace App\Tables;

use App\Base\Table;

class ExpenseCategory extends Table
{
    protected string $table      = 'expense_categories';
    protected string $primaryKey = 'id';
    protected bool   $timestamps = false;
    protected array  $fillable   = ['business_id', 'name', 'sort_order'];
    protected array  $guarded    = ['id'];
}
