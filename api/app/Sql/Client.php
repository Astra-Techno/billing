<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class Client extends Sql
{
    public function all(array $input = []): Query
    {
        return (new Query('Client.all'))
            ->from('clients c')
            ->select('list', 'c.id, c.name, c.company, c.gstin, c.state_id, c.mobile, c.currency, c.credit_days, c.active')
            ->filter('c.business_id = {business_id}')
            ->filter('c.active = 1')
            ->order('c.name', 'asc');
    }

    public function list(array $input = []): Query
    {
        return (new Query('Client.list'))
            ->from('clients c')
            ->left('indian_states s ON s.id = c.state_id')
            ->select('list', '
                c.id, c.type, c.name, c.company, c.gstin, c.email, c.mobile,
                c.city, c.credit_days, c.currency, c.active, c.created_at,
                s.name AS state_name,
                (SELECT COALESCE(SUM(i.amount_due),0) FROM invoices i WHERE i.client_id = c.id AND i.status IN (\'sent\',\'partial\',\'overdue\')) AS outstanding_balance
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('c.business_id = {business_id}')
            ->filterOptional('c.active = {filter.active}')
            ->filterOptional('c.type = {filter.type}')
            ->filterOptional('(c.name LIKE {filter.search} OR c.mobile LIKE {filter.search} OR c.gstin LIKE {filter.search})')
            ->order('{sort_by}', '{sort_order}');
    }

    public function entity(array $input = []): Query
    {
        return (new Query('Client.entity'))
            ->from('clients c')
            ->left('indian_states s ON s.id = c.state_id')
            ->select('entity', '
                c.*, s.name AS state_name, s.code AS state_code, s.is_ut,
                (SELECT COALESCE(SUM(i.amount_due),0) FROM invoices i WHERE i.client_id = c.id AND i.status IN (\'sent\',\'partial\',\'overdue\')) AS outstanding_balance
            ')
            ->select('list', '
                c.*, s.name AS state_name, s.code AS state_code, s.is_ut,
                (SELECT COALESCE(SUM(i.amount_due),0) FROM invoices i WHERE i.client_id = c.id AND i.status IN (\'sent\',\'partial\',\'overdue\')) AS outstanding_balance
            ')
            ->filter('c.id = {id}')
            ->filter('c.business_id = {business_id}');
    }

    public function contacts(array $input = []): Query
    {
        return (new Query('Client.contacts'))
            ->from('client_contacts cc')
            ->select('list', '
                cc.id, cc.client_id, cc.name, cc.designation,
                cc.email, cc.mobile, cc.whatsapp,
                cc.is_primary, cc.send_invoice, cc.notes, cc.active
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('cc.client_id = {client_id}')
            ->filter('cc.business_id = {business_id}')
            ->filterOptional('cc.active = {filter.active}')
            ->order('cc.is_primary', 'desc');
    }

    public function options(array $input = []): Query
    {
        return (new Query('Client.options'))
            ->from('clients c')
            ->select('list',    'c.id, c.name, c.company, c.gstin, c.state_id, c.currency, c.credit_days')
            ->select('options', 'c.id, c.name, c.company, c.gstin, c.state_id, c.currency, c.credit_days')
            ->filter('c.business_id = {business_id}')
            ->filter('c.active = 1')
            ->filterOptional('(c.name LIKE {filter.search} OR c.company LIKE {filter.search})')
            ->order('c.name', 'asc');
    }

    public function outstanding(array $input = []): Query
    {
        return (new Query('Client.outstanding'))
            ->from('clients c')
            ->inner('invoices i ON i.client_id = c.id AND i.business_id = c.business_id AND i.status IN (\'sent\',\'partial\',\'overdue\')')
            ->select('list', '
                c.id, c.name, c.company, c.mobile,
                COUNT(i.id) AS invoice_count,
                SUM(i.amount_due) AS total_outstanding
            ')
            ->select('total', 'COUNT(DISTINCT c.id) AS total')
            ->filter('c.business_id = {business_id}')
            ->group('c.id')
            ->order('total_outstanding', 'desc');
    }
}
