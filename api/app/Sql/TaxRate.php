<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class TaxRate extends Sql
{
    public function all(array $input = []): Query
    {
        return (new Query('TaxRate.all'))
            ->from('tax_rates t')
            ->select('list', 't.id, t.name, t.rate, t.cgst_rate, t.sgst_rate, t.igst_rate, t.utgst_rate, t.is_default')
            ->filter('t.business_id = {business_id}')
            ->filter('t.active = 1')
            ->order('t.rate', 'asc');
    }

    public function list(array $input = []): Query
    {
        return (new Query('TaxRate.list'))
            ->from('tax_rates t')
            ->select('list',    't.id, t.name, t.rate, t.cgst_rate, t.sgst_rate, t.igst_rate, t.utgst_rate, t.is_default, t.active')
            ->select('total',   'COUNT(*) AS total')
            ->select('options', 't.id, t.name, t.rate, t.cgst_rate, t.sgst_rate, t.igst_rate, t.utgst_rate, t.is_default')
            ->filter('t.business_id = {business_id}')
            ->filterOptional('t.active = {filter.active}')
            ->order('t.rate', 'asc');
    }

    public function entity(array $input = []): Query
    {
        return (new Query('TaxRate.entity'))
            ->from('tax_rates t')
            ->select('entity', 't.*')
            ->select('list',   't.*')
            ->filter('t.id = {id}')
            ->filter('t.business_id = {business_id}');
    }
}
