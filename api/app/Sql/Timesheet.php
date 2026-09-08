<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class Timesheet extends Sql
{
    public function list(array $input = []): Query
    {
        return (new Query('Timesheet.list'))
            ->from('timesheets t')
            ->left('users u ON u.id = t.user_id')
            ->left('users a ON a.id = t.approved_by')
            ->select('list', '
                t.id, t.user_id, t.work_date, t.hours, t.description,
                t.project, t.status, t.approved_at, t.created_at,
                u.name AS user_name,
                a.name AS approved_by_name
            ')
            ->select('total', 'COUNT(*) AS total')
            ->filter('t.business_id = {business_id}')
            ->filterOptional('t.user_id = {filter.user_id}')
            ->filterOptional('t.status = {filter.status}')
            ->filterOptional('t.work_date >= {filter.from_date}')
            ->filterOptional('t.work_date <= {filter.to_date}')
            ->filterOptional('(t.description LIKE {filter.search} OR t.project LIKE {filter.search} OR u.name LIKE {filter.search})')
            ->order('{sort_by}', '{sort_order}');
    }

    public function entity(array $input = []): Query
    {
        return (new Query('Timesheet.entity'))
            ->from('timesheets t')
            ->left('users u ON u.id = t.user_id')
            ->select('entity', 't.*, u.name AS user_name')
            ->select('list',   't.*, u.name AS user_name')
            ->filter('t.id = {id}')
            ->filter('t.business_id = {business_id}');
    }

    /** Staff member's own entries */
    public function my(array $input = []): Query
    {
        return (new Query('Timesheet.my'))
            ->from('timesheets t')
            ->select('list', 't.id, t.work_date, t.hours, t.description, t.project, t.status, t.created_at')
            ->select('total', 'COUNT(*) AS total')
            ->filter('t.business_id = {business_id}')
            ->filter('t.user_id = {user_id}')
            ->filterOptional('t.status = {filter.status}')
            ->filterOptional('t.work_date >= {filter.from_date}')
            ->filterOptional('t.work_date <= {filter.to_date}')
            ->order('t.work_date', 'desc');
    }
}
