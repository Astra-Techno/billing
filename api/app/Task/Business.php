<?php

namespace App\Task;

use App\Base\Task;
use App\Core\Auth;
use App\Core\DB;
use App\Tables\Business as BusinessTable;
use App\Tables\BusinessUser;
use App\Tables\Subscription;
use App\Tables\TaxRate;
use App\Tables\ExpenseCategory;

class Business extends Task
{
    protected bool $useTransaction = true;

    // ── Setup: create a new business (called during registration) ─────────────

    public function setup(array $input): array
    {
        $this->validate([
            'name'          => 'required|string|min_length:2',
            'business_type' => 'required|in:proprietorship,partnership,llp,private_ltd,public_ltd,trust,society,other',
            'mobile'        => 'required|string',
            'state_id'      => 'required|integer',
        ]);

        $userId = $this->userId();
        if (!$userId) $this->fail('Unauthenticated.', 401);

        // Generate slug
        $slug = $this->generateSlug($input['name']);

        $business = BusinessTable::create([
            'owner_id'        => $userId,
            'name'            => trim($input['name']),
            'slug'            => $slug,
            'business_type'   => $input['business_type'],
            'mobile'          => $input['mobile'],
            'email'           => $input['email']          ?? null,
            'state_id'        => (int)$input['state_id'],
            'gstin'           => $input['gstin']          ?? null,
            'pan'             => $input['pan']            ?? null,
            'is_gst_registered' => !empty($input['gstin']) ? 1 : 0,
            'invoice_prefix'  => strtoupper($input['invoice_prefix'] ?? 'INV'),
            'quote_prefix'    => strtoupper($input['quote_prefix']   ?? 'QTE'),
            'active'          => 1,
        ]);

        // Owner membership
        BusinessUser::create([
            'business_id' => $business->id,
            'user_id'     => $userId,
            'role'        => 'owner',
            'accepted_at' => date('Y-m-d H:i:s'),
            'active'      => 1,
        ]);

        // Free plan subscription (30-day trial)
        $freePlan = DB::selectOne("SELECT id FROM plans WHERE slug = 'free' LIMIT 1");
        Subscription::create([
            'business_id'          => $business->id,
            'plan_id'              => $freePlan ? $freePlan->id : 1,
            'status'               => 'trialing',
            'billing_cycle'        => 'monthly',
            'trial_ends_at'        => date('Y-m-d', strtotime('+30 days')),
            'current_period_start' => date('Y-m-d'),
            'current_period_end'   => date('Y-m-d', strtotime('+30 days')),
        ]);

        // Seed default GST tax rates
        $this->seedDefaultTaxRates((int)$business->id);

        // Seed default expense categories
        $this->seedDefaultExpenseCategories((int)$business->id);

        return $this->success([
            'business_id' => $business->id,
            'name'        => $business->name,
            'slug'        => $business->slug,
        ], 'Business created successfully.');
    }

    // ── Update basic profile ──────────────────────────────────────────────────

    public function updateProfile(array $input): array
    {
        $this->validate([
            'name'   => 'required|string|min_length:2',
            'mobile' => 'required|string',
        ]);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $business = BusinessTable::findOrFail($businessId);
        $business->fill([
            'name'            => trim($input['name']),
            'business_type'   => $input['business_type']  ?? $business->business_type,
            'email'           => $input['email']           ?? $business->email,
            'mobile'          => $input['mobile'],
            'phone'           => $input['phone']           ?? $business->phone,
            'website'         => $input['website']         ?? $business->website,
            'address_line1'   => $input['address_line1']  ?? $business->address_line1,
            'address_line2'   => $input['address_line2']  ?? $business->address_line2,
            'city'            => $input['city']            ?? $business->city,
            'state_id'        => $input['state_id']        ?? $business->state_id,
            'pincode'         => $input['pincode']         ?? $business->pincode,
            'invoice_prefix'  => strtoupper($input['invoice_prefix'] ?? $business->invoice_prefix),
            'quote_prefix'    => strtoupper($input['quote_prefix']   ?? $business->quote_prefix),
            'invoice_terms'   => $input['invoice_terms']  ?? $business->invoice_terms,
            'invoice_notes'   => $input['invoice_notes']  ?? $business->invoice_notes,
            'date_format'     => $input['date_format']    ?? $business->date_format,
        ]);
        $business->save();

        return $this->success(['business_id' => $businessId], 'Profile updated.');
    }

    // ── Upload company logo ───────────────────────────────────────────────────

    public function uploadLogo(array $input): array
    {
        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $data = $input['logo'] ?? '';
        if (!$data) $this->fail('No image data provided.');

        // Expect base64 data URL: data:image/png;base64,....
        if (!preg_match('/^data:(image\/(png|jpg|jpeg|gif|webp|svg\+xml));base64,(.+)$/i', $data, $m)) {
            $this->fail('Invalid image format. Use PNG, JPG, GIF, or WebP.');
        }
        $mime    = $m[1];
        $ext     = strtolower(str_replace(['svg+xml'], ['svg'], $m[2]));
        $raw     = base64_decode($m[3]);
        if (!$raw || strlen($raw) > 2 * 1024 * 1024) $this->fail('Image too large. Max 2 MB.');

        $logoDir = dirname(__DIR__, 2) . '/storage/logos';
        if (!is_dir($logoDir)) mkdir($logoDir, 0755, true);

        // Delete old logo file if exists
        $business = BusinessTable::findOrFail($businessId);
        if ($business->logo && str_contains($business->logo, '/storage/logos/')) {
            $old = dirname(__DIR__, 2) . parse_url($business->logo, PHP_URL_PATH);
            if (file_exists($old)) @unlink($old);
        }

        $filename = 'biz_' . $businessId . '_' . time() . '.' . $ext;
        file_put_contents($logoDir . '/' . $filename, $raw);

        $url = rtrim($_ENV['APP_URL'] ?? '', '/') . '/storage/logos/' . $filename;
        $business->fill(['logo' => $url]);
        $business->save();

        return $this->success(['logo' => $url], 'Logo uploaded.');
    }

    // ── Remove company logo ───────────────────────────────────────────────────

    public function removeLogo(array $input): array
    {
        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $business = BusinessTable::findOrFail($businessId);
        if ($business->logo && str_contains($business->logo, '/storage/logos/')) {
            $old = dirname(__DIR__, 2) . parse_url($business->logo, PHP_URL_PATH);
            if (file_exists($old)) @unlink($old);
        }
        $business->fill(['logo' => null]);
        $business->save();

        return $this->success([], 'Logo removed.');
    }

    // ── Update GST details ────────────────────────────────────────────────────

    public function updateGst(array $input): array
    {
        $this->validate([
            'gstin' => 'required|string',
        ]);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $gstin = strtoupper(trim($input['gstin']));
        if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
            $this->fail('Invalid GSTIN format. Must be 15 characters (e.g. 29AABCU9603R1Z6).');
        }

        $business = BusinessTable::findOrFail($businessId);
        $business->fill([
            'gstin'               => $gstin,
            'pan'                 => $input['pan']                  ?? $business->pan,
            'cin'                 => $input['cin']                  ?? $business->cin,
            'is_gst_registered'   => 1,
            'gst_registered_date' => $input['gst_registered_date'] ?? $business->gst_registered_date,
        ]);
        $business->save();

        return $this->success(null, 'GST details updated.');
    }

    // ── Update bank details ───────────────────────────────────────────────────

    public function updateBank(array $input): array
    {
        $this->validate([
            'bank_name'         => 'required|string',
            'bank_account_no'   => 'required|string',
            'bank_ifsc'         => 'required|string',
            'bank_account_name' => 'required|string',
        ]);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $ifsc = strtoupper(trim($input['bank_ifsc']));
        if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) {
            $this->fail('Invalid IFSC code format (e.g. SBIN0001234).');
        }

        $business = BusinessTable::findOrFail($businessId);
        $business->fill([
            'bank_name'         => $input['bank_name'],
            'bank_account_no'   => $input['bank_account_no'],
            'bank_ifsc'         => $ifsc,
            'bank_account_name' => $input['bank_account_name'],
            'upi_id'            => $input['upi_id'] ?? $business->upi_id,
        ]);
        $business->save();

        return $this->success(null, 'Bank details updated.');
    }

    // ── Invite team member ────────────────────────────────────────────────────

    public function inviteMember(array $input): array
    {
        $this->validate([
            'email' => 'required|email',
            'role'  => 'required|in:admin,accountant,staff',
        ]);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $email = strtolower(trim($input['email']));

        // Check if already a member
        $existing = DB::selectOne(
            "SELECT u.id FROM users u
             INNER JOIN business_users bu ON bu.user_id = u.id
             WHERE u.email = ? AND bu.business_id = ? LIMIT 1",
            [$email, $businessId]
        );
        if ($existing) $this->fail('This user is already a member of your business.');

        // Pending invitation
        $token = bin2hex(random_bytes(20));
        DB::statement(
            "INSERT INTO invitations (business_id, invited_by, email, role, token, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())
             ON DUPLICATE KEY UPDATE
                role = VALUES(role), token = VALUES(token),
                expires_at = VALUES(expires_at)",
            [$businessId, $this->userId(), $email, $input['role'], $token]
        );

        return $this->success([
            'invitation_token' => $token,
            'email'            => $email,
        ], 'Invitation sent.');
    }

    // ── Remove team member ────────────────────────────────────────────────────

    public function removeMember(array $input): array
    {
        $this->validate(['user_id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $targetUserId = (int)$input['user_id'];
        if ($targetUserId === $this->userId()) $this->fail('You cannot remove yourself.');

        // Ensure target is not owner
        $member = DB::selectOne(
            "SELECT role FROM business_users WHERE business_id = ? AND user_id = ? LIMIT 1",
            [$businessId, $targetUserId]
        );
        if (!$member) $this->fail('User is not a member.');
        if ($member->role === 'owner') $this->fail('Cannot remove the owner.');

        DB::statement(
            "UPDATE business_users SET active = 0 WHERE business_id = ? AND user_id = ?",
            [$businessId, $targetUserId]
        );

        return $this->success(null, 'Member removed.');
    }

    // ── Get current plan / subscription info ──────────────────────────────────

    public function planInfo(array $input): array
    {
        $businessId = $this->requireBusiness();

        $sub = DB::selectOne(
            "SELECT s.*, p.name AS plan_name, p.slug AS plan_slug,
                    p.max_users, p.max_clients, p.max_invoices_month, p.max_storage_mb,
                    p.feature_quotes, p.feature_recurring, p.feature_reports,
                    p.feature_einvoice, p.feature_ewaybill, p.feature_whatsapp,
                    p.feature_custom_logo, p.feature_multi_user, p.feature_api
             FROM subscriptions s
             INNER JOIN plans p ON p.id = s.plan_id
             WHERE s.business_id = ?
             ORDER BY s.id DESC LIMIT 1",
            [$businessId]
        );

        return $this->success($sub ? (array)$sub : [], 'Plan info loaded.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function generateSlug(string $name): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $slug = trim($slug, '-');
        $base = $slug;
        $i    = 1;
        while (DB::selectOne("SELECT id FROM businesses WHERE slug = ? LIMIT 1", [$slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function seedDefaultTaxRates(int $businessId): void
    {
        $rates = [
            ['GST 0%',  0,  0,   0,   0,   0,   1],
            ['GST 5%',  5,  2.5, 2.5, 5,   2.5, 0],
            ['GST 12%', 12, 6,   6,   12,  6,   0],
            ['GST 18%', 18, 9,   9,   18,  9,   0],
            ['GST 28%', 28, 14,  14,  28,  14,  0],
        ];

        foreach ($rates as [$name, $rate, $cgst, $sgst, $igst, $utgst, $isDefault]) {
            TaxRate::create([
                'business_id' => $businessId,
                'name'        => $name,
                'rate'        => $rate,
                'cgst_rate'   => $cgst,
                'sgst_rate'   => $sgst,
                'igst_rate'   => $igst,
                'utgst_rate'  => $utgst,
                'is_default'  => $isDefault,
                'active'      => 1,
            ]);
        }
    }

    private function seedDefaultExpenseCategories(int $businessId): void
    {
        $categories = [
            'Purchases', 'Rent', 'Salary & Wages', 'Electricity', 'Travel',
            'Office Supplies', 'Marketing', 'Bank Charges', 'Repairs', 'Other',
        ];
        foreach ($categories as $i => $name) {
            ExpenseCategory::create([
                'business_id' => $businessId,
                'name'        => $name,
                'sort_order'  => $i,
            ]);
        }
    }
}
