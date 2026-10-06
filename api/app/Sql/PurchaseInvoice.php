<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class PurchaseInvoice extends Sql
{
    public function list(array $input = []): Query
    {
        return (new Query('PurchaseInvoice.list'))
            ->from('purchase_invoices pi')
            ->inner('clients c ON c.id = pi.supplier_id')
            ->select('list', '
                pi.id, pi.number, pi.supplier_inv_no, pi.status,
                pi.invoice_date, pi.due_date,
                pi.subtotal, pi.tax_total, pi.total,
                pi.amount_paid, pi.amount_due,
                pi.created_at,
                c.id AS supplier_id, c.name AS supplier_name,
                c.company AS supplier_company, c.gstin AS supplier_gstin,
                c.mobile AS supplier_mobile
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('pi.business_id = {business_id}')
            ->filter('pi.deleted_at IS NULL')
            ->filterOptional('pi.status = {filter.status}')
            ->filterOptional('pi.supplier_id = {filter.supplier_id}')
            ->filterOptional('pi.invoice_date >= {filter.from_date}')
            ->filterOptional('pi.invoice_date <= {filter.to_date}')
            ->filterOptional('(pi.number LIKE {filter.search} OR pi.supplier_inv_no LIKE {filter.search} OR c.name LIKE {filter.search} OR c.company LIKE {filter.search} OR c.mobile LIKE {filter.search} OR c.gstin LIKE {filter.search} OR CAST(pi.total AS CHAR) LIKE {filter.search})')
            ->order('{sort_by}', '{sort_order}');
    }

    public function entity(array $input = []): Query
    {
        return (new Query('PurchaseInvoice.entity'))
            ->from('purchase_invoices pi')
            ->inner('clients c ON c.id = pi.supplier_id')
            ->left('indian_states s ON s.id = pi.place_of_supply')
            ->left('indian_states cs ON cs.id = c.state_id')
            ->left('inventory_locations il ON il.id = pi.location_id')
            ->select('entity', '
                pi.*,
                c.name AS supplier_name, c.company AS supplier_company,
                c.gstin AS supplier_gstin, c.pan AS supplier_pan,
                c.email AS supplier_email, c.mobile AS supplier_mobile,
                c.address_line1 AS supplier_address1, c.address_line2 AS supplier_address2,
                c.city AS supplier_city, c.pincode AS supplier_pincode,
                cs.name AS supplier_state_name,
                s.name AS place_of_supply_name,
                il.name AS location_name
            ')
            ->filter('pi.id = {id}')
            ->filter('pi.business_id = {business_id}')
            ->filter('pi.deleted_at IS NULL');
    }

    public function items(array $input = []): Query
    {
        return (new Query('PurchaseInvoice.items'))
            ->from('purchase_invoice_items pii')
            ->left('products p ON p.id = pii.product_id')
            ->select('list', '
                pii.id, pii.pi_id, pii.product_id, pii.description, pii.hsn_sac, pii.unit,
                pii.quantity, pii.unit_price,
                pii.discount_pct, pii.discount_amt, pii.taxable_amt,
                pii.gst_rate, pii.cgst_rate, pii.sgst_rate, pii.igst_rate,
                pii.cgst_amt, pii.sgst_amt, pii.igst_amt,
                pii.total, pii.sort_order,
                p.name AS product_name, p.type AS product_type
            ')
            ->filter('pii.pi_id = {pi_id}')
            ->order('pii.sort_order', 'asc');
    }

    public function payments(array $input = []): Query
    {
        return (new Query('PurchaseInvoice.payments'))
            ->from('purchase_payments pp')
            ->left('users u ON u.id = pp.recorded_by')
            ->select('list', '
                pp.id, pp.pi_id, pp.amount, pp.method, pp.reference,
                pp.utr_number, pp.payment_date, pp.note, pp.created_at,
                u.name AS recorded_by_name
            ')
            ->filter('pp.pi_id = {pi_id}')
            ->order('pp.payment_date', 'asc');
    }
}
