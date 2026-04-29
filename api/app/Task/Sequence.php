<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;

/**
 * Sequence — generates sequential, financial-year-aware document numbers.
 *
 * Usage inside other Tasks:
 *   $number = Task::run('Sequence.next', [
 *       'type'        => 'invoice',   // invoice|quote|credit_note|debit_note
 *       'business_id' => $businessId,
 *   ])['data']['number'];
 */
class Sequence extends Task
{
    /**
     * Generate next number for a document type in the current financial year.
     * Uses row-level locking (SELECT … FOR UPDATE) to prevent duplicate numbers.
     */
    public function next(array $input): array
    {
        $this->validate([
            'type'        => 'required|in:invoice,quote,credit_note,debit_note',
            'business_id' => 'required|integer',
        ]);

        $type       = $input['type'];
        $businessId = (int)$input['business_id'];
        $fy         = self::currentFinancialYear();

        // Get business prefix setting
        $business = DB::selectOne(
            "SELECT invoice_prefix, quote_prefix FROM businesses WHERE id = ? LIMIT 1",
            [$businessId]
        );

        $defaultPrefix = match($type) {
            'invoice'     => $business->invoice_prefix ?? 'INV',
            'quote'       => $business->quote_prefix   ?? 'QTE',
            'credit_note' => 'CN',
            'debit_note'  => 'DN',
            default       => 'DOC',
        };

        $number = DB::transaction(function() use ($type, $businessId, $fy, $defaultPrefix) {
            // Lock the sequence row
            $seq = DB::selectOne(
                "SELECT * FROM sequences
                 WHERE business_id = ? AND type = ? AND financial_year = ?
                 FOR UPDATE",
                [$businessId, $type, $fy]
            );

            if (!$seq) {
                // Create sequence for this FY
                DB::statement(
                    "INSERT INTO sequences (business_id, type, financial_year, prefix, next_number, padding)
                     VALUES (?, ?, ?, ?, 1, 4)",
                    [$businessId, $type, $fy, $defaultPrefix]
                );
                $nextNum = 1;
                $prefix  = $defaultPrefix;
                $padding = 4;
            } else {
                $nextNum = (int)$seq->next_number;
                $prefix  = $seq->prefix;
                $padding = (int)$seq->padding;
            }

            // Increment
            DB::statement(
                "UPDATE sequences SET next_number = next_number + 1
                 WHERE business_id = ? AND type = ? AND financial_year = ?",
                [$businessId, $type, $fy]
            );

            // Format: INV/2024-25/0001
            $formatted = str_pad((string)$nextNum, $padding, '0', STR_PAD_LEFT);
            return "{$prefix}/{$fy}/{$formatted}";
        });

        return $this->success(['number' => $number]);
    }

    /**
     * Update sequence settings (prefix, padding, reset number).
     */
    public function update(array $input): array
    {
        $this->validate([
            'type'    => 'required|in:invoice,quote,credit_note,debit_note',
            'prefix'  => 'required|string',
            'padding' => 'integer',
        ]);

        $businessId = $this->requireBusiness();
        $fy         = self::currentFinancialYear();

        DB::statement(
            "INSERT INTO sequences (business_id, type, financial_year, prefix, next_number, padding)
             VALUES (?, ?, ?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE
                prefix  = VALUES(prefix),
                padding = VALUES(padding)",
            [
                $businessId,
                $input['type'],
                $fy,
                strtoupper(trim($input['prefix'])),
                (int)($input['padding'] ?? 4),
            ]
        );

        return $this->success(null, 'Sequence settings updated.');
    }

    // ── Static helper (used by other Tasks directly) ──────────────────────────

    public static function generate(int $businessId, string $type): string
    {
        $result = static::run('Sequence.next', [
            'type'        => $type,
            'business_id' => $businessId,
        ]);
        return $result['data']['number'];
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    /**
     * Returns current Indian financial year as "2024-25".
     * FY runs April 1 – March 31.
     */
    public static function currentFinancialYear(): string
    {
        $month = (int)date('n');
        $year  = (int)date('Y');
        if ($month >= 4) {
            return $year . '-' . substr((string)($year + 1), 2);
        }
        return ($year - 1) . '-' . substr((string)$year, 2);
    }

    /**
     * Returns FY start date for a given FY string ("2024-25" → "2024-04-01").
     */
    public static function fyStartDate(string $fy): string
    {
        $year = (int)explode('-', $fy)[0];
        return "{$year}-04-01";
    }

    /**
     * Returns FY end date ("2024-25" → "2025-03-31").
     */
    public static function fyEndDate(string $fy): string
    {
        $year = (int)explode('-', $fy)[0] + 1;
        return "{$year}-03-31";
    }
}
