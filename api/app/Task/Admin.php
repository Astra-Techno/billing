<?php

namespace App\Task;

use App\Base\Task;
use App\Core\Auth;
use App\Core\DB;

class Admin extends Task
{
    protected array $rules = [];

    // ── Guard: only super admins may call these ───────────────────────────────

    private function requireSuperAdmin(): void
    {
        $user = Auth::user();
        if (!$user || !(int)($user->is_super_admin ?? 0)) {
            $this->fail('Super admin access required.', 403);
        }
    }

    // ── Platform stats ────────────────────────────────────────────────────────

    public function stats(array $input): array
    {
        $this->requireSuperAdmin();
        $data = $this->sql('Admin.stats')->one();
        return $this->success($data);
    }

    // ── Businesses list ───────────────────────────────────────────────────────

    public function businesses(array $input): array
    {
        $this->requireSuperAdmin();
        $rows = $this->sql('Admin.businesses', $input)->all();
        return $this->success($rows);
    }

    // ── Users list ────────────────────────────────────────────────────────────

    public function users(array $input): array
    {
        $this->requireSuperAdmin();
        $rows = $this->sql('Admin.users', $input)->all();
        return $this->success($rows);
    }

    // ── Suspend / activate a business ────────────────────────────────────────

    public function toggleBusiness(array $input): array
    {
        $this->requireSuperAdmin();
        $this->validate(['business_id' => 'required|integer']);

        $id   = (int)$input['business_id'];
        $biz  = DB::selectOne("SELECT id, active FROM businesses WHERE id = ? LIMIT 1", [$id]);
        if (!$biz) $this->fail('Business not found.', 404);

        $newActive = $biz->active ? 0 : 1;
        DB::statement("UPDATE businesses SET active = ? WHERE id = ?", [$newActive, $id]);

        $label = $newActive ? 'activated' : 'suspended';
        return $this->success(['active' => $newActive], "Business {$label}.");
    }

    // ── Suspend / activate a user ─────────────────────────────────────────────

    public function toggleUser(array $input): array
    {
        $this->requireSuperAdmin();
        $this->validate(['user_id' => 'required|integer']);

        $id   = (int)$input['user_id'];
        $user = DB::selectOne("SELECT id, active FROM users WHERE id = ? LIMIT 1", [$id]);
        if (!$user) $this->fail('User not found.', 404);

        // Prevent locking out the calling super admin
        if ($id === Auth::id()) $this->fail('Cannot deactivate your own account.', 400);

        $newActive = $user->active ? 0 : 1;
        DB::statement("UPDATE users SET active = ? WHERE id = ?", [$newActive, $id]);

        $label = $newActive ? 'activated' : 'suspended';
        return $this->success(['active' => $newActive], "User {$label}.");
    }
}
