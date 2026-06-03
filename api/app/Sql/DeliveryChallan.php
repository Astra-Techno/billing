<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class DeliveryChallan extends Sql
{
    public function list(array $input = []): Query
    {
        return (new Query('DeliveryChallan.list'))
            ->from('delivery_challans d')
            ->inner('clients c ON c.id = d.client_id')
            ->select('list', '
                d.id, d.number, d.status,
                d.challan_date, d.vehicle_no, d.driver_name, d.destination,
                d.created_at,
                c.id AS client_id, c.name AS client_name, c.company AS client_company,
                c.mobile AS client_mobile, c.gstin AS client_gstin
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('d.business_id = {business_id}')
            ->filterOptional('d.status = {filter.status}')
            ->filterOptional('d.client_id = {filter.client_id}')
            ->filterOptional('d.challan_date >= {filter.from_date}')
            ->filterOptional('d.challan_date <= {filter.to_date}')
            ->filterOptional('(d.number LIKE {filter.search} OR c.name LIKE {filter.search})')
            ->order('{sort_by}', '{sort_order}');
    }

    public function entity(array $input = []): Query
    {
        return (new Query('DeliveryChallan.entity'))
            ->from('delivery_challans d')
            ->inner('clients c ON c.id = d.client_id')
            ->select('entity', '
                d.*,
                c.name AS client_name, c.company AS client_company,
                c.gstin AS client_gstin, c.pan AS client_pan,
                c.email AS client_email, c.mobile AS client_mobile,
                c.address_line1 AS client_address1, c.address_line2 AS client_address2,
                c.city AS client_city, c.pincode AS client_pincode
            ')
            ->select('list', '
                d.*,
                c.name AS client_name, c.company AS client_company,
                c.gstin AS client_gstin, c.mobile AS client_mobile
            ')
            ->filter('d.id = {id}')
            ->filter('d.business_id = {business_id}');
    }

    public function items(array $input = []): Query
    {
        return (new Query('DeliveryChallan.items'))
            ->from('delivery_challan_items di')
            ->left('products p ON p.id = di.product_id')
            ->select('list', '
                di.id, di.delivery_challan_id AS dc_id, di.product_id, di.description, di.hsn_sac, di.unit,
                di.quantity, di.sort_order,
                p.name AS product_name, p.type AS product_type
            ')
            ->filter('di.delivery_challan_id = {dc_id}')
            ->order('di.sort_order', 'asc');
    }
}
