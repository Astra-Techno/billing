<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;

/**
 * Key-value settings scoped per business.
 * Keys like: invoice_template, default_payment_terms, email_signature, etc.
 */
class Settings extends Task
{
    // ── Get all settings for current business ─────────────────────────────────

    public function get(array $input): array
    {
        $businessId = $this->requireBusiness();

        $rows = DB::select(
            "SELECT `key`, `value` FROM settings WHERE business_id = ?",
            [$businessId]
        );

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row->key] = $row->value;
        }

        return $this->success($settings);
    }

    // ── Set one or many settings ──────────────────────────────────────────────

    public function set(array $input): array
    {
        $this->validate(['settings' => 'required']);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $settings = $input['settings'] ?? [];
        if (!is_array($settings) || empty($settings))
            $this->fail('Settings must be a non-empty key-value object.');

        foreach ($settings as $key => $value) {
            $key = preg_replace('/[^a-z0-9_.]/', '_', strtolower((string)$key));
            DB::statement(
                "INSERT INTO settings (business_id, `key`, `value`, created_at, updated_at)
                 VALUES (?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()",
                [$businessId, $key, is_array($value) ? json_encode($value) : (string)$value]
            );
        }

        return $this->success(null, 'Settings saved.');
    }

    // ── Delete a setting ──────────────────────────────────────────────────────

    public function delete(array $input): array
    {
        $this->validate(['key' => 'required|string']);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        DB::statement(
            "DELETE FROM settings WHERE business_id = ? AND `key` = ?",
            [$businessId, $input['key']]
        );

        return $this->success(null, 'Setting removed.');
    }
}
