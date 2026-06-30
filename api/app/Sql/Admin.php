<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class Admin extends Sql
{
    // ── Platform-wide stats ───────────────────────────────────────────────────

    public function stats(array $input = []): Query
    {
        return (new Query('Admin.stats'))
            ->from('businesses b')
            ->select('list', '
                (SELECT COUNT(*) FROM businesses WHERE active = 1)                AS total_businesses,
                (SELECT COUNT(*) FROM users WHERE active = 1)                      AS total_users,
                (SELECT COUNT(*) FROM invoices WHERE deleted_at IS NULL)                                    AS total_invoices,
                (SELECT COALESCE(SUM(total), 0) FROM invoices WHERE status != \'cancelled\' AND deleted_at IS NULL) AS total_billed
            ');
    }

    // ── Businesses list ───────────────────────────────────────────────────────

    public function businesses(array $input = []): Query
    {
        return (new Query('Admin.businesses'))
            ->from('businesses b')
            ->left('business_users bu ON bu.business_id = b.id AND bu.role = \'owner\'')
            ->left('users u ON u.id = bu.user_id')
            ->select('list', '
                b.id, b.name, b.slug, b.email, b.mobile, b.gstin, b.active,
                b.created_at,
                u.name AS owner_name,
                u.email AS owner_email,
                (SELECT COUNT(*) FROM invoices i WHERE i.business_id = b.id AND i.deleted_at IS NULL) AS invoice_count
            ')
            ->filterOptional('b.active = {filter.active}')
            ->group('b.id')
            ->order('b.created_at', 'desc');
    }

    // ── Users list ────────────────────────────────────────────────────────────

    public function users(array $input = []): Query
    {
        return (new Query('Admin.users'))
            ->from('users u')
            ->select('list', '
                u.id, u.name, u.email, u.mobile, u.active, u.is_super_admin, u.created_at,
                (SELECT COUNT(DISTINCT bu.business_id) FROM business_users bu WHERE bu.user_id = u.id AND bu.active = 1) AS business_count
            ')
            ->filterOptional('u.active = {filter.active}')
            ->order('u.created_at', 'desc');
    }
}
