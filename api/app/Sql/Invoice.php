<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class Invoice extends Sql
{
    public function list(array $input = []): Query
    {
        return (new Query('Invoice.list'))
            ->from('invoices i')
            ->inner('clients c ON c.id = i.client_id')
            ->left('indian_states s ON s.id = i.place_of_supply')
            ->select('list', '
                i.id, i.number, i.invoice_type, i.status,
                i.issue_date, i.due_date, i.financial_year,
                i.total, i.amount_paid, i.amount_due,
                i.supply_type, i.is_recurring, i.sent_at, i.paid_at, i.created_at,
                c.id AS client_id, c.name AS client_name, c.company AS client_company,
                c.mobile AS client_mobile, c.gstin AS client_gstin,
                s.name AS place_of_supply_name
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('i.business_id = {business_id}')
            ->filterOptional('i.status = {filter.status}')
            ->filterOptional('i.client_id = {filter.client_id}')
            ->filterOptional('i.invoice_type = {filter.invoice_type}')
            ->filterOptional('i.financial_year = {filter.financial_year}')
            ->filterOptional('i.issue_date >= {filter.from_date}')
            ->filterOptional('i.issue_date <= {filter.to_date}')
            ->filterOptional('(i.number LIKE {filter.search} OR c.name LIKE {filter.search})')
            ->order('{sort_by}', '{sort_order}');
    }

    public function entity(array $input = []): Query
    {
        return (new Query('Invoice.entity'))
            ->from('invoices i')
            ->inner('clients c ON c.id = i.client_id')
            ->left('indian_states s ON s.id = i.place_of_supply')
            ->left('indian_states bs ON bs.id = (SELECT state_id FROM businesses WHERE id = i.business_id)')
            ->select('entity', '
                i.*,
                c.name AS client_name, c.company AS client_company,
                c.gstin AS client_gstin, c.pan AS client_pan,
                c.email AS client_email, c.mobile AS client_mobile,
                c.address_line1 AS client_address1, c.address_line2 AS client_address2,
                c.city AS client_city, c.pincode AS client_pincode,
                s.name AS place_of_supply_name, s.code AS place_of_supply_code
            ')
            ->select('list', '
                i.*,
                c.name AS client_name, c.company AS client_company,
                c.gstin AS client_gstin, c.mobile AS client_mobile,
                s.name AS place_of_supply_name
            ')
            ->filter('i.id = {id}')
            ->filter('i.business_id = {business_id}');
    }

    public function items(array $input = []): Query
    {
        return (new Query('Invoice.items'))
            ->from('invoice_items ii')
            ->left('products p ON p.id = ii.product_id')
            ->select('list', '
                ii.id, ii.invoice_id, ii.product_id, ii.description, ii.hsn_sac, ii.unit,
                ii.quantity, ii.unit_price, ii.discount_pct, ii.discount_amt, ii.taxable_amt,
                ii.gst_rate, ii.cgst_rate, ii.sgst_rate, ii.igst_rate, ii.utgst_rate,
                ii.cgst_amt, ii.sgst_amt, ii.igst_amt, ii.utgst_amt,
                ii.total, ii.sort_order,
                p.name AS product_name, p.type AS product_type
            ')
            ->filter('ii.invoice_id = {invoice_id}')
            ->order('ii.sort_order', 'asc');
    }

    public function payments(array $input = []): Query
    {
        return (new Query('Invoice.payments'))
            ->from('payments p')
            ->left('users u ON u.id = p.recorded_by')
            ->select('list', '
                p.id, p.amount, p.method, p.reference, p.utr_number,
                p.payment_date, p.note, p.created_at,
                u.name AS recorded_by_name
            ')
            ->filter('p.invoice_id = {invoice_id}')
            ->filter('p.business_id = {business_id}')
            ->order('p.payment_date', 'desc');
    }

    public function overdue(array $input = []): Query
    {
        return (new Query('Invoice.overdue'))
            ->from('invoices i')
            ->inner('clients c ON c.id = i.client_id')
            ->select('list', '
                i.id, i.number, i.due_date, i.total, i.amount_due,
                c.name AS client_name, c.mobile AS client_mobile,
                DATEDIFF(CURDATE(), i.due_date) AS days_overdue
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('i.business_id = {business_id}')
            ->filter('i.status IN (\'sent\',\'partial\')')
            ->filter('i.due_date < CURDATE()')
            ->order('days_overdue', 'desc');
    }

    public function recurring(array $input = []): Query
    {
        return (new Query('Invoice.recurring'))
            ->from('invoices i')
            ->inner('clients c ON c.id = i.client_id')
            ->select('list', '
                i.id, i.number, i.recur_every, i.recur_period, i.next_recur_date,
                i.recur_ends_at, i.total, c.name AS client_name
            ')
            ->filter('i.business_id = {business_id}')
            ->filter('i.is_recurring = 1')
            ->filter('i.status != \'cancelled\'')
            ->filterOptional('i.next_recur_date <= {filter.due_before}')
            ->order('i.next_recur_date', 'asc');
    }
}
