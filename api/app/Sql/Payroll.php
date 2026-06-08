<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class Payroll extends Sql
{
    public function list(array $input = []): Query
    {
        return (new Query('Payroll.list'))
            ->from('payroll_runs pr')
            ->left('staff_members sm ON sm.id = pr.staff_id')
            ->select('list', '
                pr.*, sm.name AS staff_name, sm.role AS staff_role, sm.mobile AS staff_mobile
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('pr.business_id = {business_id}')
            ->filter('pr.month = {month}')
            ->filter('pr.year = {year}')
            ->order('sm.name', 'asc');
    }

    public function summary(array $input = []): Query
    {
        return (new Query('Payroll.summary'))
            ->from('payroll_runs')
            ->select('list', '
                year, month,
                COUNT(*) AS staff_count,
                SUM(net_pay) AS total_pay,
                SUM(CASE WHEN status = \'paid\' THEN 1 ELSE 0 END) AS paid_count
            ')
            ->filter('business_id = {business_id}')
            ->group('year, month')
            ->order('year', 'desc')
            ->order('month', 'desc');
    }
}
