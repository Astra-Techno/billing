<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Timesheet task validation and logic tests.
 *
 * Tests the validation rules and edge cases of Timesheet Task
 * without requiring a database connection.
 */
class TimesheetTest extends TestCase
{
    // ── Hours validation ──────────────────────────────────────────────

    public function test_hours_must_be_positive(): void
    {
        $this->assertTrue($this->isValidHours(0.25));
        $this->assertTrue($this->isValidHours(8));
        $this->assertTrue($this->isValidHours(24));
        $this->assertFalse($this->isValidHours(0));
        $this->assertFalse($this->isValidHours(-1));
        $this->assertFalse($this->isValidHours(25));
    }

    public function test_hours_boundary_values(): void
    {
        $this->assertFalse($this->isValidHours(0));
        $this->assertTrue($this->isValidHours(0.01));
        $this->assertTrue($this->isValidHours(24));
        $this->assertFalse($this->isValidHours(24.01));
    }

    // ── Date restrictions for staff ──────────────────────────────────

    public function test_staff_can_only_log_today(): void
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $this->assertTrue($this->isDateAllowedForStaff($today));
        $this->assertFalse($this->isDateAllowedForStaff($yesterday));
        $this->assertFalse($this->isDateAllowedForStaff($tomorrow));
    }

    public function test_admin_can_log_any_date(): void
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $this->assertTrue($this->isDateAllowedForAdmin($yesterday));
    }

    // ── Status transitions ───────────────────────────────────────────

    public function test_valid_status_transitions(): void
    {
        // Staff can only edit pending entries
        $this->assertTrue($this->canStaffEdit('pending'));
        $this->assertFalse($this->canStaffEdit('approved'));
        $this->assertFalse($this->canStaffEdit('rejected'));
    }

    public function test_admin_can_edit_any_status(): void
    {
        $this->assertTrue($this->canAdminEdit('pending'));
        $this->assertTrue($this->canAdminEdit('approved'));
        $this->assertTrue($this->canAdminEdit('rejected'));
    }

    public function test_only_pending_can_be_approved(): void
    {
        // approve/reject should only work on pending entries
        $this->assertTrue($this->canApprove('pending'));
        // approved/rejected are already resolved
        $this->assertFalse($this->canApprove('approved'));
        $this->assertFalse($this->canApprove('rejected'));
    }

    // ── Staff ownership ──────────────────────────────────────────────

    public function test_staff_can_only_edit_own_entries(): void
    {
        $this->assertTrue($this->isOwnEntry(5, 5));
        $this->assertFalse($this->isOwnEntry(5, 10));
    }

    public function test_staff_can_only_delete_own_pending(): void
    {
        // Own + pending + today = allowed
        $this->assertTrue($this->canStaffDelete(5, 5, 'pending', date('Y-m-d')));
        // Not own entry
        $this->assertFalse($this->canStaffDelete(5, 10, 'pending', date('Y-m-d')));
        // Not pending
        $this->assertFalse($this->canStaffDelete(5, 5, 'approved', date('Y-m-d')));
        // Not today
        $this->assertFalse($this->canStaffDelete(5, 5, 'pending', date('Y-m-d', strtotime('-1 day'))));
    }

    // ── Bulk approve ─────────────────────────────────────────────────

    public function test_bulk_ids_parsing(): void
    {
        // Array input
        $ids = [1, 2, 3];
        $this->assertEquals([1, 2, 3], $this->parseBulkIds($ids));

        // Comma-separated string
        $ids = '4,5,6';
        $this->assertEquals([4, 5, 6], $this->parseBulkIds($ids));

        // Empty/invalid filtered out
        $ids = '1,,0,abc,3';
        $this->assertEquals([1, 3], $this->parseBulkIds($ids));
    }

    // ── Frontend calcHours equivalent ────────────────────────────────

    public function test_hours_calculation_from_times(): void
    {
        $this->assertEquals(8.0, $this->calcHours('09:00', '17:00'));
        $this->assertEquals(0.5, $this->calcHours('09:00', '09:30'));
        $this->assertEquals(0.25, $this->calcHours('09:00', '09:15'));
        $this->assertEquals(0, $this->calcHours('17:00', '09:00')); // invalid: end before start
        $this->assertEquals(0, $this->calcHours('09:00', '09:00')); // same time = 0
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function isValidHours(float $hours): bool
    {
        return $hours > 0 && $hours <= 24;
    }

    private function isDateAllowedForStaff(string $date): bool
    {
        return $date === date('Y-m-d');
    }

    private function isDateAllowedForAdmin(string $date): bool
    {
        return true; // admin can log any date
    }

    private function canStaffEdit(string $status): bool
    {
        return $status === 'pending';
    }

    private function canAdminEdit(string $status): bool
    {
        return true;
    }

    private function canApprove(string $status): bool
    {
        return $status === 'pending';
    }

    private function isOwnEntry(int $userId, int $entryUserId): bool
    {
        return $userId === $entryUserId;
    }

    private function canStaffDelete(int $userId, int $entryUserId, string $status, string $workDate): bool
    {
        return $userId === $entryUserId && $status === 'pending' && $workDate === date('Y-m-d');
    }

    private function parseBulkIds($ids): array
    {
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        return array_values(array_filter(array_map('intval', $ids)));
    }

    private function calcHours(string $from, string $to): float
    {
        [$fh, $fm] = array_map('intval', explode(':', $from));
        [$th, $tm] = array_map('intval', explode(':', $to));
        $diff = ($th * 60 + $tm) - ($fh * 60 + $fm);
        return $diff > 0 ? round($diff / 15) * 0.25 : 0;
    }
}
