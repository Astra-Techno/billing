<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class EwayBill extends Sql
{
    public function list(array $input = []): Query
    {
        return (new Query('EwayBill.list'))
            ->from('eway_bills e')
            ->inner('invoices i ON i.id = e.invoice_id')
            ->select('list', '
                e.id, e.ewb_number, e.status, e.mode,
                e.distance, e.vehicle_no, e.vehicle_type, e.transporter,
                e.valid_from, e.valid_until, e.created_at,
                e.invoice_id,
                i.number AS invoice_number, i.invoice_date,
                i.total AS invoice_total
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('e.business_id = {business_id}')
            ->filterOptional('e.invoice_id = {filter.invoice_id}')
            ->filterOptional('e.status = {filter.status}')
            ->order('{sort_by}', '{sort_order}');
    }

    public function entity(array $input = []): Query
    {
        return (new Query('EwayBill.entity'))
            ->from('eway_bills e')
            ->inner('invoices i ON i.id = e.invoice_id')
            ->left('clients c ON c.id = i.client_id')
            ->select('entity', '
                e.*,
                i.number AS invoice_number, i.invoice_date,
                i.total AS invoice_total,
                c.name AS client_name, c.company AS client_company,
                c.gstin AS client_gstin
            ')
            ->filter('e.id = {id}')
            ->filter('e.business_id = {business_id}');
    }
}
