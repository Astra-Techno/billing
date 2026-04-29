<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class Expense extends Sql
{
    public function list(array $input = []): Query
    {
        return (new Query('Expense.list'))
            ->from('expenses e')
            ->left('expense_categories ec ON ec.id = e.category_id')
            ->left('users u ON u.id = e.recorded_by')
            ->select('list',  '
                e.id, e.vendor_name, e.description, e.amount, e.gst_amount, e.total_amount,
                e.expense_date, e.method, e.reference, e.financial_year, e.created_at,
                ec.name AS category_name,
                u.name AS recorded_by_name
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('e.business_id = {business_id}')
            ->filterOptional('e.category_id = {filter.category_id}')
            ->filterOptional('e.method = {filter.method}')
            ->filterOptional('e.financial_year = {filter.financial_year}')
            ->filterOptional('e.expense_date >= {filter.from_date}')
            ->filterOptional('e.expense_date <= {filter.to_date}')
            ->filterOptional('(e.vendor_name LIKE {filter.search} OR e.description LIKE {filter.search})')
            ->order('{sort_by}', '{sort_order}');
    }

    public function entity(array $input = []): Query
    {
        return (new Query('Expense.entity'))
            ->from('expenses e')
            ->left('expense_categories ec ON ec.id = e.category_id')
            ->select('entity', 'e.*, ec.name AS category_name')
            ->select('list',   'e.*, ec.name AS category_name')
            ->filter('e.id = {id}')
            ->filter('e.business_id = {business_id}');
    }

    public function categories(array $input = []): Query
    {
        return (new Query('Expense.categories'))
            ->from('expense_categories ec')
            ->select('list',    'ec.id, ec.name, ec.sort_order')
            ->select('options', 'ec.id, ec.name')
            ->filter('ec.business_id = {business_id}')
            ->order('ec.sort_order', 'asc');
    }
}
