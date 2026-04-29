<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class Report extends Sql
{
    // ── GSTR-1 style: B2B sales ───────────────────────────────

    public function gstrB2b(array $input = []): Query
    {
        return (new Query('Report.gstrB2b'))
            ->from('invoices i')
            ->inner('clients c ON c.id = i.client_id')
            ->left('indian_states s ON s.id = i.place_of_supply')
            ->select('list', '
                i.number, i.issue_date, i.invoice_type,
                c.name AS client_name, c.gstin AS client_gstin,
                s.name AS place_of_supply, s.code AS pos_code,
                i.supply_type, i.reverse_charge,
                i.subtotal AS taxable_value,
                i.cgst_total, i.sgst_total, i.igst_total, i.utgst_total,
                i.tax_total, i.total
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('i.business_id = {business_id}')
            ->filter('i.financial_year = {financial_year}')
            ->filter('i.status != \'cancelled\'')
            ->filter('c.gstin IS NOT NULL AND c.gstin != \'\'')
            ->filterOptional('MONTH(i.issue_date) = {filter.month}')
            ->order('i.issue_date', 'asc');
    }

    // ── GSTR-1 style: B2C sales ───────────────────────────────

    public function gstrB2c(array $input = []): Query
    {
        return (new Query('Report.gstrB2c'))
            ->from('invoices i')
            ->inner('clients c ON c.id = i.client_id')
            ->left('indian_states s ON s.id = i.place_of_supply')
            ->select('list', '
                i.number, i.issue_date, i.invoice_type,
                c.name AS client_name,
                s.name AS place_of_supply, s.code AS pos_code,
                i.supply_type,
                i.subtotal AS taxable_value,
                i.cgst_total, i.sgst_total, i.igst_total,
                i.tax_total, i.total
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('i.business_id = {business_id}')
            ->filter('i.financial_year = {financial_year}')
            ->filter('i.status != \'cancelled\'')
            ->filter('(c.gstin IS NULL OR c.gstin = \'\')')
            ->filterOptional('MONTH(i.issue_date) = {filter.month}')
            ->order('i.issue_date', 'asc');
    }

    // ── HSN/SAC summary ───────────────────────────────────────

    public function hsnSummary(array $input = []): Query
    {
        return (new Query('Report.hsnSummary'))
            ->from('invoice_items ii')
            ->inner('invoices i ON i.id = ii.invoice_id')
            ->select('list', '
                ii.hsn_sac,
                ii.unit,
                ii.gst_rate,
                COUNT(DISTINCT i.id)   AS invoice_count,
                SUM(ii.quantity)       AS total_qty,
                SUM(ii.taxable_amt)    AS taxable_value,
                SUM(ii.cgst_amt)       AS cgst_amt,
                SUM(ii.sgst_amt)       AS sgst_amt,
                SUM(ii.igst_amt)       AS igst_amt,
                SUM(ii.total)          AS total_value
            ')
            ->filter('i.business_id = {business_id}')
            ->filter('i.financial_year = {financial_year}')
            ->filter('i.status != \'cancelled\'')
            ->filterOptional('ii.hsn_sac = {filter.hsn_sac}')
            ->group('ii.hsn_sac, ii.unit, ii.gst_rate')
            ->order('taxable_value', 'desc');
    }

    // ── Profit & Loss ─────────────────────────────────────────

    public function profitLoss(array $input = []): Query
    {
        return (new Query('Report.profitLoss'))
            ->from('invoices i')
            ->select('list', '
                DATE_FORMAT(i.issue_date, \'%Y-%m\')  AS month,
                SUM(i.subtotal)                        AS revenue,
                SUM(i.tax_total)                       AS gst_collected,
                SUM(i.total)                           AS gross_revenue,
                SUM(i.amount_paid)                     AS collected
            ')
            ->filter('i.business_id = {business_id}')
            ->filter('i.status != \'cancelled\'')
            ->filterOptional('i.financial_year = {financial_year}')
            ->group('DATE_FORMAT(i.issue_date, \'%Y-%m\')')
            ->order('month', 'asc');
    }

    // ── Outstanding / Ageing ──────────────────────────────────

    public function ageing(array $input = []): Query
    {
        return (new Query('Report.ageing'))
            ->from('invoices i')
            ->inner('clients c ON c.id = i.client_id')
            ->select('list', '
                c.id AS client_id, c.name AS client_name, c.mobile AS client_mobile,
                i.id AS invoice_id, i.number, i.issue_date, i.due_date,
                i.total, i.amount_paid, i.amount_due,
                DATEDIFF(CURDATE(), i.due_date) AS days_overdue,
                CASE
                    WHEN DATEDIFF(CURDATE(), i.due_date) <= 0   THEN \'current\'
                    WHEN DATEDIFF(CURDATE(), i.due_date) <= 30  THEN \'1-30_days\'
                    WHEN DATEDIFF(CURDATE(), i.due_date) <= 60  THEN \'31-60_days\'
                    WHEN DATEDIFF(CURDATE(), i.due_date) <= 90  THEN \'61-90_days\'
                    ELSE \'90plus_days\'
                END AS ageing_bucket
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('i.business_id = {business_id}')
            ->filter('i.status IN (\'sent\',\'partial\',\'overdue\')')
            ->filter('i.amount_due > 0')
            ->filterOptional('i.client_id = {filter.client_id}')
            ->order('days_overdue', 'desc');
    }

    // ── Payment collection report ─────────────────────────────

    public function paymentCollection(array $input = []): Query
    {
        return (new Query('Report.paymentCollection'))
            ->from('payments p')
            ->inner('clients c ON c.id = p.client_id')
            ->inner('invoices i ON i.id = p.invoice_id')
            ->select('list', '
                p.payment_date, p.amount, p.method, p.reference, p.utr_number,
                c.name AS client_name, c.gstin AS client_gstin,
                i.number AS invoice_number
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('p.business_id = {business_id}')
            ->filterOptional('p.method = {filter.method}')
            ->filterOptional('p.payment_date >= {filter.from_date}')
            ->filterOptional('p.payment_date <= {filter.to_date}')
            ->filterOptional('i.financial_year = {filter.financial_year}')
            ->order('p.payment_date', 'desc');
    }
}
