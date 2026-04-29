<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class Quote extends Sql
{
    public function list(array $input = []): Query
    {
        return (new Query('Quote.list'))
            ->from('quotes q')
            ->inner('clients c ON c.id = q.client_id')
            ->select('list',  '
                q.id, q.number, q.type, q.status,
                q.issue_date, q.valid_until, q.financial_year,
                q.total, q.sent_at, q.accepted_at, q.converted_at, q.created_at,
                c.id AS client_id, c.name AS client_name, c.company AS client_company
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('q.business_id = {business_id}')
            ->filterOptional('q.status = {filter.status}')
            ->filterOptional('q.client_id = {filter.client_id}')
            ->filterOptional('q.financial_year = {filter.financial_year}')
            ->filterOptional('q.issue_date >= {filter.from_date}')
            ->filterOptional('q.issue_date <= {filter.to_date}')
            ->filterOptional('(q.number LIKE {filter.search} OR c.name LIKE {filter.search})')
            ->order('{sort_by}', '{sort_order}');
    }

    public function entity(array $input = []): Query
    {
        return (new Query('Quote.entity'))
            ->from('quotes q')
            ->inner('clients c ON c.id = q.client_id')
            ->left('indian_states s ON s.id = q.place_of_supply')
            ->select('entity', '
                q.*,
                c.name AS client_name, c.company AS client_company,
                c.gstin AS client_gstin, c.email AS client_email, c.mobile AS client_mobile,
                c.address_line1 AS client_address1, c.address_line2 AS client_address2,
                c.city AS client_city, c.pincode AS client_pincode,
                s.name AS place_of_supply_name, s.code AS place_of_supply_code
            ')
            ->select('list', 'q.*, c.name AS client_name, c.gstin AS client_gstin')
            ->filter('q.id = {id}')
            ->filter('q.business_id = {business_id}');
    }

    public function items(array $input = []): Query
    {
        return (new Query('Quote.items'))
            ->from('quote_items qi')
            ->left('products p ON p.id = qi.product_id')
            ->select('list', '
                qi.id, qi.quote_id, qi.product_id, qi.description, qi.hsn_sac, qi.unit,
                qi.quantity, qi.unit_price, qi.discount_pct, qi.discount_amt, qi.taxable_amt,
                qi.gst_rate, qi.cgst_rate, qi.sgst_rate, qi.igst_rate,
                qi.cgst_amt, qi.sgst_amt, qi.igst_amt,
                qi.total, qi.sort_order,
                p.name AS product_name
            ')
            ->filter('qi.quote_id = {quote_id}')
            ->order('qi.sort_order', 'asc');
    }
}
