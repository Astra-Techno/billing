<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class PurchaseOrder extends Sql
{
    public function list(array $input = []): Query
    {
        return (new Query('PurchaseOrder.list'))
            ->from('purchase_orders po')
            ->inner('clients c ON c.id = po.supplier_id')
            ->select('list', '
                po.id, po.number, po.status,
                po.order_date, po.expected_date,
                po.subtotal, po.tax_total, po.total,
                po.created_at,
                c.id AS supplier_id, c.name AS supplier_name,
                c.company AS supplier_company, c.gstin AS supplier_gstin,
                c.mobile AS supplier_mobile
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('po.business_id = {business_id}')
            ->filterOptional('po.status = {filter.status}')
            ->filterOptional('po.supplier_id = {filter.supplier_id}')
            ->filterOptional('po.order_date >= {filter.from_date}')
            ->filterOptional('po.order_date <= {filter.to_date}')
            ->filterOptional('(po.number LIKE {filter.search} OR c.name LIKE {filter.search})')
            ->order('{sort_by}', '{sort_order}');
    }

    public function entity(array $input = []): Query
    {
        return (new Query('PurchaseOrder.entity'))
            ->from('purchase_orders po')
            ->inner('clients c ON c.id = po.supplier_id')
            ->select('entity', '
                po.*,
                c.name AS supplier_name, c.company AS supplier_company,
                c.gstin AS supplier_gstin, c.pan AS supplier_pan,
                c.email AS supplier_email, c.mobile AS supplier_mobile,
                c.address_line1 AS supplier_address1, c.address_line2 AS supplier_address2,
                c.city AS supplier_city, c.pincode AS supplier_pincode
            ')
            ->filter('po.id = {id}')
            ->filter('po.business_id = {business_id}');
    }

    public function items(array $input = []): Query
    {
        return (new Query('PurchaseOrder.items'))
            ->from('purchase_order_items poi')
            ->left('products p ON p.id = poi.product_id')
            ->select('list', '
                poi.id, poi.po_id, poi.product_id, poi.description, poi.hsn_sac, poi.unit,
                poi.quantity, poi.unit_price, poi.gst_rate, poi.total, poi.sort_order,
                p.name AS product_name, p.type AS product_type
            ')
            ->filter('poi.po_id = {po_id}')
            ->order('poi.sort_order', 'asc');
    }
}
