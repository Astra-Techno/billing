<?php

namespace App\Task;

use App\Base\Task;
use App\Core\Auth as AuthCore;
use App\Core\DB;
use App\Tables\User;

class Auth extends Task
{
    protected array $rules = [];

    // ── Login ─────────────────────────────────────────────────────────────────

    public function login(array $input): array
    {
        $this->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $email = strtolower(trim($input['email'] ?? ''));

        $row = DB::selectOne(
            "SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1",
            [$email]
        );

        if (!$row || !password_verify($input['password'] ?? '', $row->password ?? ''))
            $this->fail('Invalid email or password.', 401);

        // Load user's businesses
        $businesses = DB::select(
            "SELECT b.id, b.name, b.slug, b.logo, bu.role
             FROM businesses b
             INNER JOIN business_users bu ON bu.business_id = b.id AND bu.user_id = ? AND bu.active = 1
             WHERE b.active = 1
             ORDER BY b.name",
            [$row->id]
        );

        // Auto-select if only one business, generate scoped token
        $businessId = null;
        if (count($businesses) === 1) {
            $businessId = (int)$businesses[0]->id;
        } elseif (!empty($input['business_id'])) {
            $businessId = (int)$input['business_id'];
        }

        $token = AuthCore::createToken((int)$row->id, 'web', $businessId);

        return $this->success([
            'token'       => $token,
            'user'        => [
                'id'     => $row->id,
                'name'   => $row->name,
                'email'  => $row->email,
                'mobile' => $row->mobile,
                'avatar' => $row->avatar,
            ],
            'businesses'  => $businesses,
            'business_id' => $businessId,
        ], 'Login successful.');
    }

    // ── Register user + create first business ─────────────────────────────────

    public function register(array $input): array
    {
        $this->validate([
            'name'                  => 'required|string|min_length:2',
            'email'                 => 'required|email|unique:users,email',
            'mobile'                => 'required|string',
            'password'              => 'required|min_length:8|confirmed',
            'password_confirmation' => 'required',
            'business_name'         => 'required|string|min_length:2',
            'business_type'         => 'required|in:proprietorship,partnership,llp,private_ltd,public_ltd,trust,society,other',
            'state_id'              => 'required|integer',
        ]);

        return DB::transaction(function() use ($input) {
            // 1. Create global user
            $user = User::create([
                'name'     => trim($input['name']),
                'email'    => strtolower(trim($input['email'])),
                'mobile'   => $input['mobile'],
                'password' => password_hash($input['password'], PASSWORD_BCRYPT),
                'active'   => 1,
            ]);

            // 2. Create business (Business Task handles GST seed + categories)
            AuthCore::setUser($user);         // needed so Business Task can call userId()
            AuthCore::setBusinessId(null);    // no business yet

            $bizResult = static::run('Business.setup', [
                'name'          => trim($input['business_name']),
                'business_type' => $input['business_type'],
                'mobile'        => $input['mobile'],
                'email'         => $input['email'],
                'state_id'      => (int)$input['state_id'],
                'gstin'         => $input['gstin']          ?? null,
                'invoice_prefix'=> $input['invoice_prefix'] ?? 'INV',
                'quote_prefix'  => $input['quote_prefix']   ?? 'QTE',
            ]);

            $businessId = (int)$bizResult['data']['business_id'];

            // 3. Create scoped auth token
            $token = AuthCore::createToken((int)$user->id, 'web', $businessId);

            return $this->success([
                'token'       => $token,
                'user'        => [
                    'id'     => $user->id,
                    'name'   => $user->name,
                    'email'  => $user->email,
                    'mobile' => $user->mobile,
                ],
                'business_id' => $businessId,
            ], 'Welcome! Your account and business have been created.');
        });
    }

    // ── Switch active business ────────────────────────────────────────────────

    public function switchBusiness(array $input): array
    {
        $this->validate(['business_id' => 'required|integer']);

        $userId     = $this->userId();
        $businessId = (int)$input['business_id'];

        $member = DB::selectOne(
            "SELECT bu.role, b.name
             FROM business_users bu
             INNER JOIN businesses b ON b.id = bu.business_id AND b.active = 1
             WHERE bu.business_id = ? AND bu.user_id = ? AND bu.active = 1 LIMIT 1",
            [$businessId, $userId]
        );

        if (!$member) $this->fail('Access denied to this business.', 403);

        // Create new scoped token, revoke old
        AuthCore::deleteCurrentToken();
        $token = AuthCore::createToken($userId, 'web', $businessId);

        return $this->success([
            'token'         => $token,
            'business_id'   => $businessId,
            'business_name' => $member->name,
            'role'          => $member->role,
        ], "Switched to {$member->name}.");
    }

    // ── Change password ───────────────────────────────────────────────────────

    public function changePassword(array $input): array
    {
        $this->validate([
            'current_password'      => 'required',
            'password'              => 'required|min_length:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        $userId = $this->userId();
        if (!$userId) $this->fail('Unauthenticated.', 401);

        $user = User::findOrFail($userId);
        if (!password_verify($input['current_password'], $user->password))
            $this->fail('Current password is incorrect.', 422);

        $user->setAttribute('password', password_hash($input['password'], PASSWORD_BCRYPT));
        $user->save();

        return $this->success(null, 'Password changed successfully.');
    }

    // ── Update personal profile ───────────────────────────────────────────────

    public function updateProfile(array $input): array
    {
        $this->validate([
            'name' => 'required|string|min_length:2',
        ]);

        $userId = $this->userId();
        if (!$userId) $this->fail('Unauthenticated.', 401);

        $user = User::findOrFail($userId);
        $user->fill([
            'name'   => trim($input['name']),
            'mobile' => $input['mobile'] ?? $user->mobile,
        ]);
        $user->save();

        return $this->success([
            'id'     => $user->id,
            'name'   => $user->name,
            'email'  => $user->email,
            'mobile' => $user->mobile,
        ], 'Profile updated.');
    }

    // ── Logout (delete current token) ─────────────────────────────────────────

    public function logout(array $input): array
    {
        AuthCore::deleteCurrentToken();
        return $this->success(null, 'Logged out successfully.');
    }

    // ── Logout all devices ────────────────────────────────────────────────────

    public function logoutAll(array $input): array
    {
        $userId = $this->userId();
        if (!$userId) $this->fail('Unauthenticated.', 401);

        AuthCore::deleteAllTokens($userId);
        return $this->success(null, 'All sessions terminated.');
    }
}
