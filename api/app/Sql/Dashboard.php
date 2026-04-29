<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class Dashboard extends Sql
{
    // ── Quick stats for dashboard header cards ────────────────

    public function stats(array $input = []): Query
    {
        return (new Query('Dashboard.stats'))
            ->from('invoices i')
            ->select('list', '
                SUM(i.amount_due)                                                       AS total_due,
                SUM(CASE WHEN i.status = \'overdue\'                 THEN 1 ELSE 0 END) AS overdue_count,
                SUM(CASE WHEN i.status = \'draft\'                   THEN 1 ELSE 0 END) AS draft_count,
                SUM(CASE WHEN i.status = \'paid\'
                         AND MONTH(i.paid_at) = MONTH(CURDATE())
                         AND YEAR(i.paid_at)  = YEAR(CURDATE())
                    THEN i.total ELSE 0 END)                                            AS total_paid_month
            ')
            ->filter('i.business_id = {business_id}')
            ->filter('i.status != \'cancelled\'');
    }

    // ── Summary counts & amounts ──────────────────────────────

    public function summary(array $input = []): Query
    {
        return (new Query('Dashboard.summary'))
            ->from('invoices i')
            ->select('entity', '
                COUNT(*)                                              AS total_invoices,
                SUM(i.total)                                         AS total_billed,
                SUM(i.amount_paid)                                   AS total_collected,
                SUM(i.amount_due)                                    AS total_outstanding,
                SUM(CASE WHEN i.status = \'paid\'    THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN i.status = \'overdue\' THEN 1 ELSE 0 END) AS overdue_count,
                SUM(CASE WHEN i.status IN (\'sent\',\'partial\') THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN i.status = \'draft\'   THEN 1 ELSE 0 END) AS draft_count
            ')
            ->select('list', '
                COUNT(*)                                              AS total_invoices,
                SUM(i.total)                                         AS total_billed,
                SUM(i.amount_paid)                                   AS total_collected,
                SUM(i.amount_due)                                    AS total_outstanding,
                SUM(CASE WHEN i.status = \'paid\'    THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN i.status = \'overdue\' THEN 1 ELSE 0 END) AS overdue_count,
                SUM(CASE WHEN i.status IN (\'sent\',\'partial\') THEN 1 ELSE 0 END) AS pending_count
            ')
            ->filter('i.business_id = {business_id}')
            ->filterOptional('i.financial_year = {financial_year}');
    }

    // ── Monthly revenue (last 12 months) ─────────────────────

    public function monthlyRevenue(array $input = []): Query
    {
        return (new Query('Dashboard.monthlyRevenue'))
            ->from('payments p')
            ->select('list', '
                DATE_FORMAT(p.payment_date, \'%Y-%m\') AS month,
                SUM(p.amount)                          AS collected
            ')
            ->filter('p.business_id = {business_id}')
            ->filter('p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)')
            ->group('DATE_FORMAT(p.payment_date, \'%Y-%m\')')
            ->order('month', 'asc');
    }

    // ── Top clients by outstanding ────────────────────────────

    public function topOutstandingClients(array $input = []): Query
    {
        return (new Query('Dashboard.topOutstandingClients'))
            ->from('invoices i')
            ->inner('clients c ON c.id = i.client_id')
            ->select('list', '
                c.id, c.name, c.company, c.mobile,
                COUNT(i.id)       AS invoice_count,
                SUM(i.amount_due) AS outstanding
            ')
            ->filter('i.business_id = {business_id}')
            ->filter('i.status IN (\'sent\',\'partial\',\'overdue\')')
            ->filter('i.amount_due > 0')
            ->group('c.id')
            ->order('outstanding', 'desc');
    }

    // ── Recent activity ───────────────────────────────────────

    public function recentInvoices(array $input = []): Query
    {
        return (new Query('Dashboard.recentInvoices'))
            ->from('invoices i')
            ->inner('clients c ON c.id = i.client_id')
            ->select('list', '
                i.id, i.number, i.status, i.total, i.amount_due, i.due_date, i.created_at,
                c.name AS client_name
            ')
            ->filter('i.business_id = {business_id}')
            ->order('i.created_at', 'desc');
    }

    public function recentPayments(array $input = []): Query
    {
        return (new Query('Dashboard.recentPayments'))
            ->from('payments p')
            ->inner('clients c ON c.id = p.client_id')
            ->inner('invoices i ON i.id = p.invoice_id')
            ->select('list', '
                p.id, p.amount, p.method, p.payment_date,
                c.name AS client_name,
                i.number AS invoice_number
            ')
            ->filter('p.business_id = {business_id}')
            ->order('p.created_at', 'desc');
    }

    // ── GST summary for a financial year ─────────────────────

    public function gstSummary(array $input = []): Query
    {
        return (new Query('Dashboard.gstSummary'))
            ->from('invoices i')
            ->select('list', '
                i.financial_year,
                SUM(i.subtotal)    AS taxable_value,
                SUM(i.cgst_total)  AS total_cgst,
                SUM(i.sgst_total)  AS total_sgst,
                SUM(i.igst_total)  AS total_igst,
                SUM(i.utgst_total) AS total_utgst,
                SUM(i.tax_total)   AS total_tax,
                SUM(i.total)       AS total_invoice_value
            ')
            ->filter('i.business_id = {business_id}')
            ->filter('i.status != \'cancelled\'')
            ->filterOptional('i.financial_year = {financial_year}')
            ->filterOptional('i.supply_type = {filter.supply_type}')
            ->group('i.financial_year')
            ->order('i.financial_year', 'desc');
    }

    // ── Expense summary ───────────────────────────────────────

    public function expenseSummary(array $input = []): Query
    {
        return (new Query('Dashboard.expenseSummary'))
            ->from('expenses e')
            ->left('expense_categories ec ON ec.id = e.category_id')
            ->select('list', '
                ec.name AS category,
                COUNT(e.id)             AS count,
                SUM(e.total_amount)     AS total
            ')
            ->filter('e.business_id = {business_id}')
            ->filterOptional('e.financial_year = {financial_year}')
            ->group('e.category_id')
            ->order('total', 'desc');
    }
}
