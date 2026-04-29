<?php

namespace App\Sql;

use App\Base\Query;
use App\Base\Sql;

class Business extends Sql
{
    public function entity(array $input = []): Query
    {
        return (new Query('Business.entity'))
            ->from('businesses b')
            ->left('indian_states s ON s.id = b.state_id')
            ->select('entity', 'b.*, s.name AS state_name, s.code AS state_code, s.is_ut')
            ->select('list',   'b.*, s.name AS state_name, s.code AS state_code, s.is_ut')
            ->filter('b.id = {business_id}');
    }

    public function byOwner(array $input = []): Query
    {
        return (new Query('Business.byOwner'))
            ->from('businesses b')
            ->left('indian_states s ON s.id = b.state_id')
            ->left('subscriptions sub ON sub.business_id = b.id AND sub.status IN (\'trialing\',\'active\')')
            ->left('plans p ON p.id = sub.plan_id')
            ->select('list', '
                b.id, b.name, b.slug, b.gstin, b.is_gst_registered, b.logo, b.currency,
                b.active, b.created_at,
                s.name AS state_name,
                p.name AS plan_name, p.slug AS plan_slug,
                sub.status AS sub_status, sub.trial_ends_at, sub.current_period_end
            ')
            ->filter('b.owner_id = {owner_id}')
            ->filterOptional('b.active = {filter.active}')
            ->order('b.created_at', 'desc');
    }

    public function withPlan(array $input = []): Query
    {
        return (new Query('Business.withPlan'))
            ->from('businesses b')
            ->left('indian_states s ON s.id = b.state_id')
            ->left('subscriptions sub ON sub.business_id = b.id AND sub.status IN (\'trialing\',\'active\')')
            ->left('plans p ON p.id = sub.plan_id')
            ->select('entity', '
                b.*,
                s.name AS state_name, s.code AS state_code, s.is_ut,
                p.id AS plan_id, p.name AS plan_name, p.slug AS plan_slug,
                p.max_users, p.max_clients, p.max_invoices_month, p.max_storage_mb,
                p.feature_quotes, p.feature_recurring, p.feature_reports,
                p.feature_einvoice, p.feature_ewaybill, p.feature_whatsapp,
                p.feature_custom_logo, p.feature_multi_user, p.feature_api,
                sub.status AS sub_status, sub.trial_ends_at,
                sub.current_period_start, sub.current_period_end, sub.billing_cycle
            ')
            ->select('list', '
                b.id, b.name, b.slug, b.logo, b.gstin, b.is_gst_registered, b.active,
                p.name AS plan_name, p.slug AS plan_slug,
                sub.status AS sub_status, sub.trial_ends_at
            ')
            ->filter('b.id = {id}');
    }
}
