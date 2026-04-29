<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class ExpenseCategory extends Sql
{
    public function all(array $input = []): Query
    {
        return (new Query('ExpenseCategory.all'))
            ->from('expense_categories ec')
            ->select('list', 'ec.id, ec.name, ec.sort_order')
            ->filter('ec.business_id = {business_id}')
            ->order('ec.sort_order', 'asc');
    }
}
