<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class Product extends Sql
{
    public function all(array $input = []): Query
    {
        return (new Query('Product.all'))
            ->from('products p')
            ->left('tax_rates t ON t.id = p.tax_rate_id')
            ->select('list', 'p.id, p.name, p.type, p.hsn_sac, p.unit, p.price, p.tax_rate_id, t.rate AS gst_rate')
            ->filter('p.business_id = {business_id}')
            ->filter('p.active = 1')
            ->order('p.name', 'asc');
    }

    public function list(array $input = []): Query
    {
        return (new Query('Product.list'))
            ->from('products p')
            ->left('tax_rates t ON t.id = p.tax_rate_id')
            ->select('list',    'p.id, p.type, p.name, p.hsn_sac, p.unit, p.price, p.sku, p.active, t.name AS tax_name, t.rate AS gst_rate')
            ->select('total',   'COUNT(*) AS total')
            ->select('options', 'p.id, p.name, p.type, p.hsn_sac, p.unit, p.price, p.tax_rate_id, t.rate AS gst_rate, t.cgst_rate, t.sgst_rate, t.igst_rate')
            ->filter('p.business_id = {business_id}')
            ->filterOptional('p.active = {filter.active}')
            ->filterOptional('p.type = {filter.type}')
            ->filterOptional('(p.name LIKE {filter.search} OR p.hsn_sac LIKE {filter.search} OR p.sku LIKE {filter.search})')
            ->order('{sort_by}', '{sort_order}');
    }

    public function entity(array $input = []): Query
    {
        return (new Query('Product.entity'))
            ->from('products p')
            ->left('tax_rates t ON t.id = p.tax_rate_id')
            ->select('entity', 'p.*, t.name AS tax_name, t.rate AS gst_rate, t.cgst_rate, t.sgst_rate, t.igst_rate, t.utgst_rate')
            ->select('list',   'p.*, t.name AS tax_name, t.rate AS gst_rate, t.cgst_rate, t.sgst_rate, t.igst_rate, t.utgst_rate')
            ->filter('p.id = {id}')
            ->filter('p.business_id = {business_id}');
    }
}
