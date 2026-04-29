<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Tables\Notification as NotificationTable;

class Notification extends Task
{
    // ── Mark single notification as read ──────────────────────────────────────

    public function markRead(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $userId     = $this->userId();
        $businessId = $this->requireBusiness();

        DB::statement(
            "UPDATE notifications
             SET read_at = NOW()
             WHERE id = ? AND user_id = ? AND business_id = ? AND read_at IS NULL",
            [(int)$input['id'], $userId, $businessId]
        );

        return $this->success(null, 'Notification marked as read.');
    }

    // ── Mark all as read ──────────────────────────────────────────────────────

    public function markAllRead(array $input): array
    {
        $userId     = $this->userId();
        $businessId = $this->requireBusiness();

        DB::statement(
            "UPDATE notifications
             SET read_at = NOW()
             WHERE user_id = ? AND business_id = ? AND read_at IS NULL",
            [$userId, $businessId]
        );

        return $this->success(null, 'All notifications marked as read.');
    }

    // ── Delete a notification ─────────────────────────────────────────────────

    public function delete(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $userId = $this->userId();
        DB::statement(
            "DELETE FROM notifications WHERE id = ? AND user_id = ?",
            [(int)$input['id'], $userId]
        );

        return $this->success(null, 'Notification deleted.');
    }

    // ── Internal: push notification to a user ─────────────────────────────────
    // Usage: Notification::push($businessId, $userId, 'payment_received', 'Payment Received', 'message...')

    public static function push(
        int    $businessId,
        int    $userId,
        string $type,
        string $title,
        string $message = '',
        array  $data    = []
    ): void {
        DB::statement(
            "INSERT INTO notifications (business_id, user_id, type, title, message, data, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $businessId,
                $userId,
                $type,
                $title,
                $message,
                !empty($data) ? json_encode($data) : null,
            ]
        );
    }
}
