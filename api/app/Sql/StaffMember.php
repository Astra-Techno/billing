<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class StaffMember extends Sql
{
    public function list(array $input = []): Query
    {
        return (new Query('StaffMember.list'))
            ->from('staff_members sm')
            ->select('list', 'sm.id, sm.name, sm.role, sm.mobile, sm.monthly_salary, sm.join_date, sm.is_active')
            ->select('total', 'COUNT(*) AS total')
            ->filter('sm.business_id = {business_id}')
            ->filter('sm.is_active = 1')
            ->order('sm.name', 'asc');
    }

    public function entity(array $input = []): Query
    {
        return (new Query('StaffMember.entity'))
            ->from('staff_members sm')
            ->select('entity', 'sm.*')
            ->select('list',   'sm.*')
            ->filter('sm.id = {id}')
            ->filter('sm.business_id = {business_id}');
    }
}
