<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class Payment extends Sql
{
    public function list(array $input = []): Query
    {
        return (new Query('Payment.list'))
            ->from('payments p')
            ->inner('invoices i ON i.id = p.invoice_id')
            ->inner('clients c ON c.id = p.client_id')
            ->left('users u ON u.id = p.recorded_by')
            ->select('list',  '
                p.id, p.amount, p.method, p.reference, p.utr_number,
                p.payment_date, p.note, p.created_at,
                i.id AS invoice_id, i.number AS invoice_number,
                c.id AS client_id, c.name AS client_name,
                u.name AS recorded_by_name
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('p.business_id = {business_id}')
            ->filterOptional('p.client_id = {filter.client_id}')
            ->filterOptional('p.invoice_id = {filter.invoice_id}')
            ->filterOptional('p.method = {filter.method}')
            ->filterOptional('p.payment_date >= {filter.from_date}')
            ->filterOptional('p.payment_date <= {filter.to_date}')
            ->filterOptional('(i.number LIKE {filter.search} OR c.name LIKE {filter.search})')
            ->order('{sort_by}', '{sort_order}');
    }
}
