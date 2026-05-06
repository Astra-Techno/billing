<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Core\Auth as AuthCore;
use App\Tables\User;
use App\Tables\BusinessUser;

class Invite extends Task
{
    // ── Validate a token and return its details (public) ──────────────────────

    public function check(array $input): array
    {
        $this->validate(['token' => 'required|string']);

        $inv = $this->findInvite($input['token']);

        $business = DB::selectOne(
            "SELECT name FROM businesses WHERE id = ? LIMIT 1",
            [$inv->business_id]
        );

        // Check if an account already exists for this email
        $userExists = DB::selectOne(
            "SELECT id FROM users WHERE email = ? AND active = 1 LIMIT 1",
            [$inv->email]
        );

        return $this->success([
            'email'         => $inv->email,
            'role'          => $inv->role,
            'business_name' => $business->name ?? '',
            'user_exists'   => (bool)$userExists,
            'expires_at'    => $inv->expires_at,
        ]);
    }

    // ── Accept invite: register (if new) or just join (if existing user) ──────

    public function accept(array $input): array
    {
        $this->validate(['token' => 'required|string']);

        $inv = $this->findInvite($input['token']);

        return DB::transaction(function() use ($inv, $input) {
            $existingUser = DB::selectOne(
                "SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1",
                [$inv->email]
            );

            if ($existingUser) {
                // User exists — just join the business
                $user = $existingUser;
            } else {
                // New user — require name + password
                if (empty($input['name']) || empty($input['password'])) {
                    $this->fail('Name and password are required to create your account.');
                }
                if (strlen($input['password']) < 8) {
                    $this->fail('Password must be at least 8 characters.');
                }

                $user = User::create([
                    'name'     => trim($input['name']),
                    'email'    => $inv->email,
                    'mobile'   => $input['mobile'] ?? null,
                    'password' => password_hash($input['password'], PASSWORD_BCRYPT),
                    'active'   => 1,
                ]);
            }

            // Check not already a member (could have been removed and re-invited)
            $existing = DB::selectOne(
                "SELECT id, active FROM business_users WHERE business_id = ? AND user_id = ? LIMIT 1",
                [$inv->business_id, $user->id]
            );

            if ($existing) {
                // Re-activate if previously removed
                DB::statement(
                    "UPDATE business_users SET active = 1, role = ?, accepted_at = NOW() WHERE id = ?",
                    [$inv->role, $existing->id]
                );
            } else {
                BusinessUser::create([
                    'business_id' => $inv->business_id,
                    'user_id'     => $user->id,
                    'role'        => $inv->role,
                    'invited_by'  => $inv->invited_by,
                    'accepted_at' => date('Y-m-d H:i:s'),
                    'active'      => 1,
                ]);
            }

            // Mark invite as accepted
            DB::statement(
                "UPDATE invitations SET accepted_at = NOW() WHERE id = ?",
                [$inv->id]
            );

            // Create scoped token
            $token = AuthCore::createToken((int)$user->id, 'web', (int)$inv->business_id);

            // Load all businesses for this user
            $businesses = DB::select(
                "SELECT b.id, b.name, b.slug, b.logo, bu.role
                 FROM businesses b
                 INNER JOIN business_users bu ON bu.business_id = b.id AND bu.user_id = ? AND bu.active = 1
                 WHERE b.active = 1
                 ORDER BY b.name",
                [(int)$user->id]
            );

            $business = DB::selectOne("SELECT name FROM businesses WHERE id = ? LIMIT 1", [$inv->business_id]);

            return $this->success([
                'token'       => $token,
                'user'        => [
                    'id'     => $user->id,
                    'name'   => $user->name,
                    'email'  => $user->email,
                    'mobile' => $user->mobile,
                ],
                'businesses'  => $businesses,
                'business_id' => (int)$inv->business_id,
            ], "Welcome! You've joined {$business->name}.");
        });
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function findInvite(string $token): object
    {
        $inv = DB::selectOne(
            "SELECT * FROM invitations WHERE token = ? AND accepted_at IS NULL AND expires_at > NOW() LIMIT 1",
            [$token]
        );
        if (!$inv) $this->fail('This invite link is invalid or has expired.', 404);
        return $inv;
    }
}
