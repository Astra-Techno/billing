<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class User extends Sql
{
    public function default(array $input = []): Query
    {
        return $this->list($input);
    }

    public function list(array $input = []): Query
    {
        return (new Query('User.list'))
            ->from('users u')
            ->select('list',  'u.id, u.name, u.email, u.role, u.active, u.created_at')
            ->select('total', 'COUNT(*) AS total')
            ->filter('u.active = 1')
            ->filterOptional('u.role = {filter.role}')
            ->filterOptional('u.name LIKE {filter.search} OR u.email LIKE {filter.search}')
            ->order('{sort_by}', '{sort_order}');
    }

    public function entity(array $input = []): Query
    {
        return (new Query('User.entity'))
            ->from('users u')
            ->select('entity', 'u.id, u.name, u.email, u.role, u.active, u.created_at, u.updated_at')
            ->select('list',   'u.id, u.name, u.email, u.role, u.active, u.created_at, u.updated_at')
            ->filter('u.id = {id}');
    }

    public function byEmail(array $input = []): Query
    {
        return (new Query('User.byEmail'))
            ->from('users u')
            ->select('entity', 'u.*')
            ->select('list',   'u.*')
            ->filter('u.email = {email}');
    }
}
