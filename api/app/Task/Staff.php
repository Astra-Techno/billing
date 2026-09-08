<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;

class Staff extends Task
{
    // ── List members ────────────────────────────────────────────────────────

    public function list(array $input): array
    {
        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $members = DB::select(
            "SELECT u.id, u.name, u.email, u.mobile, bu.role, bu.permissions, bu.created_at AS joined_at
             FROM business_users bu
             INNER JOIN users u ON u.id = bu.user_id
             WHERE bu.business_id = ? AND bu.active = 1
             ORDER BY FIELD(bu.role,'owner','admin','accountant','staff'), u.name",
            [$businessId]
        );

        foreach ($members as &$m) {
            $m->permissions = $m->permissions ? json_decode($m->permissions, true) : null;
        }
        unset($m);

        return $this->success(['members' => $members]);
    }

    // ── Create a staff account directly ─────────────────────────────────────

    public function create(array $input): array
    {
        $this->validate([
            'name'     => 'required|string',
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
            'role'     => 'required|in:admin,accountant,staff',
        ]);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $email       = strtolower(trim($input['email']));
        $name        = trim($input['name']);
        $role        = $input['role'];
        $permissions = !empty($input['permissions']) && is_array($input['permissions'])
            ? json_encode(array_values($input['permissions']))
            : null;

        // Check if already a member of this business
        $existing = DB::selectOne(
            "SELECT bu.id FROM business_users bu
             INNER JOIN users u ON u.id = bu.user_id
             WHERE bu.business_id = ? AND u.email = ? AND bu.active = 1 LIMIT 1",
            [$businessId, $email]
        );
        if ($existing) $this->fail('This email is already a member of your business.');

        // Check if user account exists
        $user = DB::selectOne("SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);

        if ($user) {
            // User exists — just link to this business
            $userId = $user->id;
        } else {
            // Create new user account
            DB::statement(
                "INSERT INTO users (name, email, password, active, created_at, updated_at)
                 VALUES (?, ?, ?, 1, NOW(), NOW())",
                [$name, $email, password_hash($input['password'], PASSWORD_BCRYPT)]
            );
            $userId = DB::lastInsertId();
        }

        // Link user to business
        DB::statement(
            "INSERT INTO business_users (business_id, user_id, role, permissions, invited_by, accepted_at, active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE role = VALUES(role), permissions = VALUES(permissions), active = 1, updated_at = NOW()",
            [$businessId, $userId, $role, $permissions, $this->userId()]
        );

        return $this->success(null, "Staff account created for {$name}. They can login with their email and password.");
    }

    // ── Update a member's role ──────────────────────────────────────────────

    public function updateRole(array $input): array
    {
        $this->validate([
            'user_id' => 'required|integer',
            'role'    => 'required|in:admin,accountant,staff',
        ]);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $targetUserId = (int)$input['user_id'];

        $target = DB::selectOne(
            "SELECT role FROM business_users WHERE business_id = ? AND user_id = ? AND active = 1 LIMIT 1",
            [$businessId, $targetUserId]
        );
        if (!$target) $this->fail('Member not found.', 404);
        if ($target->role === 'owner') $this->fail('The owner role cannot be changed.');
        if ($targetUserId === $this->userId()) $this->fail('You cannot change your own role.');

        $permissions = !empty($input['permissions']) && is_array($input['permissions'])
            ? json_encode(array_values($input['permissions']))
            : null;

        DB::statement(
            "UPDATE business_users SET role = ?, permissions = ? WHERE business_id = ? AND user_id = ?",
            [$input['role'], $permissions, $businessId, $targetUserId]
        );

        return $this->success(null, 'Role updated.');
    }

    // ── Remove a member ─────────────────────────────────────────────────────

    public function remove(array $input): array
    {
        $this->validate(['user_id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $targetUserId = (int)$input['user_id'];

        $target = DB::selectOne(
            "SELECT role FROM business_users WHERE business_id = ? AND user_id = ? AND active = 1 LIMIT 1",
            [$businessId, $targetUserId]
        );
        if (!$target) $this->fail('Member not found.', 404);
        if ($target->role === 'owner') $this->fail('The owner cannot be removed.');
        if ($targetUserId === $this->userId()) $this->fail('You cannot remove yourself.');

        DB::statement(
            "UPDATE business_users SET active = 0 WHERE business_id = ? AND user_id = ?",
            [$businessId, $targetUserId]
        );

        return $this->success(null, 'Member removed.');
    }
}
