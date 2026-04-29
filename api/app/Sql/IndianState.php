<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class IndianState extends Sql
{
    public function all(array $input = []): Query
    {
        return (new Query('IndianState.all'))
            ->from('indian_states')
            ->select('list', 'id, name, code, is_ut')
            ->order('name', 'asc');
    }
}
