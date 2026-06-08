<?php

namespace App\Task;

use App\Base\Task;
use App\Tables\StaffMember as StaffMemberTable;

class StaffMember extends Task
{
    // ── Create staff member ───────────────────────────────────────────────────

    public function create(array $input): array
    {
        $this->validate([
            'name'           => 'required|string|min_length:2',
            'monthly_salary' => 'required|numeric',
        ]);

        $businessId = $this->requireBusiness();

        $staff = StaffMemberTable::create([
            'business_id'    => $businessId,
            'name'           => trim($input['name']),
            'role'           => $input['role']        ?? null,
            'mobile'         => $input['mobile']      ?? null,
            'email'          => $input['email']       ?? null,
            'monthly_salary' => (float)$input['monthly_salary'],
            'join_date'      => $input['join_date']   ?? null,
            'notes'          => $input['notes']       ?? null,
            'is_active'      => 1,
        ]);

        return $this->success([
            'staff_id' => $staff->id,
            'name'     => $staff->name,
        ], 'Staff member added.');
    }

    // ── Update staff member ───────────────────────────────────────────────────

    public function update(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $staff      = $this->findStaff((int)$input['id'], $businessId);

        $staff->fill([
            'name'           => isset($input['name'])           ? trim($input['name'])           : $staff->name,
            'role'           => $input['role']                  ?? $staff->role,
            'mobile'         => $input['mobile']                ?? $staff->mobile,
            'email'          => $input['email']                 ?? $staff->email,
            'monthly_salary' => isset($input['monthly_salary']) ? (float)$input['monthly_salary'] : $staff->monthly_salary,
            'join_date'      => $input['join_date']             ?? $staff->join_date,
            'notes'          => $input['notes']                 ?? $staff->notes,
        ]);
        $staff->save();

        return $this->success(['staff_id' => $staff->id], 'Staff member updated.');
    }

    // ── Soft-delete staff member ──────────────────────────────────────────────

    public function delete(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $staff      = $this->findStaff((int)$input['id'], $businessId);

        $staff->setAttribute('is_active', 0);
        $staff->save();

        return $this->success(null, 'Staff member deactivated.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function findStaff(int $id, int $businessId): object
    {
        $staff = StaffMemberTable::find($id);
        if (!$staff || (int)$staff->business_id !== $businessId)
            $this->fail('Staff member not found.', 404);
        return $staff;
    }
}
