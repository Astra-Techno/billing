<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Tables\BusinessUser;
use App\Tables\User;

class Staff extends Task
{
    // ── List members + pending invites ────────────────────────────────────────

    public function list(array $input): array
    {
        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $members = DB::select(
            "SELECT u.id, u.name, u.email, u.mobile, bu.role, bu.created_at AS joined_at
             FROM business_users bu
             INNER JOIN users u ON u.id = bu.user_id
             WHERE bu.business_id = ? AND bu.active = 1
             ORDER BY FIELD(bu.role,'owner','admin','accountant','staff'), u.name",
            [$businessId]
        );

        $pending = DB::select(
            "SELECT id, email, role, token, expires_at, created_at
             FROM invitations
             WHERE business_id = ? AND accepted_at IS NULL AND expires_at > NOW()
             ORDER BY created_at DESC",
            [$businessId]
        );

        return $this->success([
            'members' => $members,
            'pending' => $pending,
        ]);
    }

    // ── Invite a new staff member ─────────────────────────────────────────────

    public function invite(array $input): array
    {
        $this->validate([
            'email' => 'required|email',
            'role'  => 'required|in:admin,accountant,staff',
        ]);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $email = strtolower(trim($input['email']));
        $role  = $input['role'];

        // Check if already a member
        $existing = DB::selectOne(
            "SELECT bu.id FROM business_users bu
             INNER JOIN users u ON u.id = bu.user_id
             WHERE bu.business_id = ? AND u.email = ? AND bu.active = 1 LIMIT 1",
            [$businessId, $email]
        );
        if ($existing) $this->fail('This person is already a member of your business.');

        // Delete any existing pending invite for same email+business
        DB::statement(
            "DELETE FROM invitations WHERE business_id = ? AND email = ? AND accepted_at IS NULL",
            [$businessId, $email]
        );

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

        DB::statement(
            "INSERT INTO invitations (business_id, invited_by, email, role, token, expires_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$businessId, $this->userId(), $email, $role, $token, $expiresAt]
        );

        $business = DB::selectOne("SELECT name FROM businesses WHERE id = ? LIMIT 1", [$businessId]);

        $inviteUrl  = (isset($_ENV['FRONTEND_URL']) ? rtrim($_ENV['FRONTEND_URL'], '/') : '') . '/accept-invite/' . $token;
        $waMessage  = urlencode("Hi! You've been invited to join {$business->name} on CloudBill as {$role}. Accept here: {$inviteUrl}");
        $waShareUrl = "https://wa.me/?text={$waMessage}";

        return $this->success([
            'token'      => $token,
            'invite_url' => $inviteUrl,
            'wa_url'     => $waShareUrl,
            'expires_at' => $expiresAt,
        ], "Invite created for {$email}.");
    }

    // ── Cancel a pending invite ───────────────────────────────────────────────

    public function cancelInvite(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        DB::statement(
            "DELETE FROM invitations WHERE id = ? AND business_id = ?",
            [(int)$input['id'], $businessId]
        );

        return $this->success(null, 'Invite cancelled.');
    }

    // ── Update a member's role ────────────────────────────────────────────────

    public function updateRole(array $input): array
    {
        $this->validate([
            'user_id' => 'required|integer',
            'role'    => 'required|in:admin,accountant,staff',
        ]);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $targetUserId = (int)$input['user_id'];

        // Cannot change own role or owner's role
        $target = DB::selectOne(
            "SELECT role FROM business_users WHERE business_id = ? AND user_id = ? AND active = 1 LIMIT 1",
            [$businessId, $targetUserId]
        );
        if (!$target) $this->fail('Member not found.', 404);
        if ($target->role === 'owner') $this->fail('The owner role cannot be changed.');
        if ($targetUserId === $this->userId()) $this->fail('You cannot change your own role.');

        DB::statement(
            "UPDATE business_users SET role = ? WHERE business_id = ? AND user_id = ?",
            [$input['role'], $businessId, $targetUserId]
        );

        return $this->success(null, 'Role updated.');
    }

    // ── Remove a member ───────────────────────────────────────────────────────

    public function remove(array $input): array
    {
        $this->validate(['user_id' => 'required|integer']);

        $businessId   = $this->requireBusiness();
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
