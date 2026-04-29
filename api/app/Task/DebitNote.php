<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Tables\DebitNote as DebitNoteTable;

class DebitNote extends Task
{
    // ── Create debit note ─────────────────────────────────────────────────────

    public function create(array $input): array
    {
        $this->validate([
            'invoice_id' => 'required|integer',
            'reason'     => 'required|in:price_revision,shortage,other',
            'issue_date' => 'required|date',
            'subtotal'   => 'required|numeric',
        ]);

        $businessId = $this->requireBusiness();

        $invoice = DB::selectOne(
            "SELECT * FROM invoices WHERE id = ? AND business_id = ? LIMIT 1",
            [(int)$input['invoice_id'], $businessId]
        );
        if (!$invoice) $this->fail('Invoice not found.', 404);
        if ($invoice->status === 'cancelled') $this->fail('Cannot issue debit note for a cancelled invoice.');

        $supplyType = $invoice->supply_type;
        $subtotal   = (float)$input['subtotal'];
        $gstRate    = (float)($input['gst_rate'] ?? 0);

        [$cgst, $sgst, $igst] = $this->computeGst($subtotal, $gstRate, $supplyType);
        $taxTotal = $cgst + $sgst + $igst;
        $total    = round($subtotal + $taxTotal);

        $number = Sequence::generate($businessId, 'debit_note');

        DebitNoteTable::create([
            'business_id'    => $businessId,
            'created_by'     => $this->userId(),
            'invoice_id'     => (int)$input['invoice_id'],
            'client_id'      => $invoice->client_id,
            'number'         => $number,
            'reason'         => $input['reason'],
            'issue_date'     => $input['issue_date'],
            'supply_type'    => $supplyType,
            'place_of_supply'=> $invoice->place_of_supply,
            'subtotal'       => $subtotal,
            'cgst_total'     => $cgst,
            'sgst_total'     => $sgst,
            'igst_total'     => $igst,
            'tax_total'      => $taxTotal,
            'total'          => $total,
            'status'         => 'draft',
            'notes'          => $input['notes'] ?? null,
        ]);

        return $this->success(['number' => $number, 'total' => $total], 'Debit note created.');
    }

    // ── Issue debit note ──────────────────────────────────────────────────────

    public function issue(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $dn         = DebitNoteTable::find((int)$input['id']);

        if (!$dn || (int)$dn->business_id !== $businessId) $this->fail('Debit note not found.', 404);
        if ($dn->status !== 'draft') $this->fail('Only draft debit notes can be issued.');

        DB::statement(
            "UPDATE debit_notes SET status = 'issued' WHERE id = ?",
            [$dn->id]
        );

        // Increase invoice amount_due
        DB::statement(
            "UPDATE invoices
             SET amount_due = amount_due + ?,
                 status = CASE WHEN status = 'paid' THEN 'partial' ELSE status END
             WHERE id = ?",
            [(float)$dn->total, $dn->invoice_id]
        );

        return $this->success(['number' => $dn->number], 'Debit note issued and invoice balance updated.');
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function computeGst(float $taxable, float $rate, string $supplyType): array
    {
        if ($supplyType === 'intra') {
            $half = round($taxable * ($rate / 2 / 100), 2);
            return [$half, $half, 0.0];
        }
        return [0.0, 0.0, round($taxable * ($rate / 100), 2)];
    }
}
