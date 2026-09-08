<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Tables\Timesheet as TimesheetTable;

class Timesheet extends Task
{
    // ── Create timesheet entry (staff logs own time) ────────────────────────────

    public function create(array $input): array
    {
        $this->validate([
            'work_date'   => 'required|date',
            'hours'       => 'required|numeric',
            'description' => 'required|string',
        ]);

        $businessId = $this->requireBusiness();

        // Staff can only log time for today
        if (!in_array($this->getRole(), ['owner', 'admin']) && $input['work_date'] !== date('Y-m-d')) {
            $this->fail('You can only log time for today.');
        }

        $hours = (float)$input['hours'];
        if ($hours <= 0 || $hours > 24) $this->fail('Hours must be between 0.25 and 24.');

        $entry = TimesheetTable::create([
            'business_id' => $businessId,
            'user_id'     => $this->userId(),
            'work_date'   => $input['work_date'],
            'from_time'   => $input['from_time'] ?? null,
            'to_time'     => $input['to_time'] ?? null,
            'hours'       => $hours,
            'description' => trim($input['description']),
            'project'     => $input['project'] ?? null,
            'status'      => 'pending',
        ]);

        return $this->success(['id' => $entry->id], 'Timesheet entry saved.');
    }

    // ── Update own entry (only if still pending) ────────────────────────────────

    public function update(array $input): array
    {
        $this->validate([
            'id'          => 'required|integer',
            'work_date'   => 'required|date',
            'hours'       => 'required|numeric',
            'description' => 'required|string',
        ]);

        $businessId = $this->requireBusiness();
        $entry = $this->findEntry((int)$input['id'], $businessId);
        $role = $this->getRole();

        // Staff can only edit their own pending entries for today
        if ((int)$entry->user_id !== $this->userId()) {
            $this->requireRole(['owner', 'admin']);
        }
        if ($entry->status !== 'pending' && !in_array($role, ['owner', 'admin'])) {
            $this->fail('Only pending entries can be edited.');
        }
        if (!in_array($role, ['owner', 'admin']) && $input['work_date'] !== date('Y-m-d')) {
            $this->fail('You can only edit today\'s entries.');
        }

        $hours = (float)$input['hours'];
        if ($hours <= 0 || $hours > 24) $this->fail('Hours must be between 0.25 and 24.');

        $entry->fill([
            'work_date'   => $input['work_date'],
            'from_time'   => $input['from_time'] ?? $entry->from_time,
            'to_time'     => $input['to_time'] ?? $entry->to_time,
            'hours'       => $hours,
            'description' => trim($input['description']),
            'project'     => $input['project'] ?? $entry->project,
        ]);
        $entry->save();

        return $this->success(null, 'Timesheet entry updated.');
    }

    // ── Delete entry ────────────────────────────────────────────────────────────

    public function delete(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $entry = $this->findEntry((int)$input['id'], $businessId);

        // Staff can delete own pending entries for today only; owner/admin can delete any
        $isAdmin = in_array($this->getRole(), ['owner', 'admin']);
        if (!$isAdmin) {
            if ((int)$entry->user_id !== $this->userId() || $entry->status !== 'pending') {
                $this->requireRole(['owner', 'admin']);
            }
            if ($entry->work_date !== date('Y-m-d')) {
                $this->fail('You can only delete today\'s entries.');
            }
        }

        DB::statement("DELETE FROM timesheets WHERE id = ?", [$entry->id]);

        return $this->success(null, 'Entry deleted.');
    }

    // ── Approve / Reject (owner/admin only) ─────────────────────────────────────

    public function approve(array $input): array
    {
        $this->validate(['id' => 'required|integer']);
        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $entry = $this->findEntry((int)$input['id'], $businessId);
        $entry->fill([
            'status'      => 'approved',
            'approved_by' => $this->userId(),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
        $entry->save();

        return $this->success(null, 'Entry approved.');
    }

    public function reject(array $input): array
    {
        $this->validate(['id' => 'required|integer']);
        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $entry = $this->findEntry((int)$input['id'], $businessId);
        $entry->fill([
            'status'      => 'rejected',
            'approved_by' => $this->userId(),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
        $entry->save();

        return $this->success(null, 'Entry rejected.');
    }

    // ── Bulk approve ────────────────────────────────────────────────────────────

    public function bulkApprove(array $input): array
    {
        $this->validate(['ids' => 'required']);
        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);

        $ids = is_array($input['ids']) ? $input['ids'] : explode(',', $input['ids']);
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) $this->fail('No entries selected.');

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        DB::statement(
            "UPDATE timesheets SET status = 'approved', approved_by = ?, approved_at = NOW()
             WHERE id IN ($placeholders) AND business_id = ? AND status = 'pending'",
            array_merge([$this->userId()], $ids, [$businessId])
        );

        return $this->success(null, count($ids) . ' entries approved.');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function findEntry(int $id, int $businessId): object
    {
        $entry = TimesheetTable::find($id);
        if (!$entry || (int)$entry->business_id !== $businessId)
            $this->fail('Timesheet entry not found.', 404);
        return $entry;
    }

    private function getRole(): string
    {
        $businessId = $this->requireBusiness();
        $bu = DB::selectOne(
            "SELECT role FROM business_users WHERE business_id = ? AND user_id = ?",
            [$businessId, $this->userId()]
        );
        return $bu->role ?? 'staff';
    }
}
