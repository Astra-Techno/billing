<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class PurchaseReturn extends Sql
{
    public function list(array $input = []): Query
    {
        return (new Query('PurchaseReturn.list'))
            ->from('purchase_returns pr')
            ->inner('clients c ON c.id = pr.supplier_id')
            ->inner('purchase_invoices pi ON pi.id = pr.pi_id')
            ->select('list', '
                pr.id, pr.number, pr.reason, pr.status,
                pr.return_date, pr.subtotal, pr.tax_total, pr.total,
                pr.created_at,
                pi.number AS pi_number,
                c.name AS supplier_name, c.company AS supplier_company
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('pr.business_id = {business_id}')
            ->filterOptional('pr.status = {filter.status}')
            ->filterOptional('(pr.number LIKE {filter.search} OR pi.number LIKE {filter.search} OR c.name LIKE {filter.search})')
            ->order('{sort_by}', '{sort_order}');
    }

    public function entity(array $input = []): Query
    {
        return (new Query('PurchaseReturn.entity'))
            ->from('purchase_returns pr')
            ->inner('clients c ON c.id = pr.supplier_id')
            ->inner('purchase_invoices pi ON pi.id = pr.pi_id')
            ->select('entity', '
                pr.*,
                pi.number AS pi_number, pi.total AS pi_total,
                c.name AS supplier_name, c.company AS supplier_company,
                c.gstin AS supplier_gstin, c.mobile AS supplier_mobile
            ')
            ->filter('pr.id = {id}')
            ->filter('pr.business_id = {business_id}');
    }

    public function items(array $input = []): Query
    {
        return (new Query('PurchaseReturn.items'))
            ->from('purchase_return_items pri')
            ->left('products p ON p.id = pri.product_id')
            ->select('list', '
                pri.id, pri.pr_id, pri.product_id, pri.description, pri.hsn_sac, pri.unit,
                pri.quantity, pri.unit_price, pri.taxable_amt,
                pri.gst_rate, pri.cgst_amt, pri.sgst_amt, pri.igst_amt,
                pri.total, pri.batch_no, pri.expiry_date, pri.sort_order,
                p.name AS product_name
            ')
            ->filter('pri.pr_id = {pr_id}')
            ->order('pri.sort_order', 'asc');
    }
}
