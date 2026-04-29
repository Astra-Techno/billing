<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Tables\CreditNote as CreditNoteTable;
use App\Tables\CreditNoteItem;

class CreditNote extends Task
{
    protected bool $useTransaction = true;

    // ── Create credit note ────────────────────────────────────────────────────

    public function create(array $input): array
    {
        $this->validate([
            'invoice_id' => 'required|integer',
            'reason'     => 'required|in:return,discount,correction,other',
            'issue_date' => 'required|date',
            'items'      => 'required',
        ]);

        $businessId = $this->requireBusiness();
        $items      = $input['items'] ?? [];

        if (empty($items)) $this->fail('At least one item is required.');

        // Validate invoice belongs to business
        $invoice = DB::selectOne(
            "SELECT * FROM invoices WHERE id = ? AND business_id = ? LIMIT 1",
            [(int)$input['invoice_id'], $businessId]
        );
        if (!$invoice) $this->fail('Invoice not found.', 404);
        if ($invoice->status === 'cancelled') $this->fail('Cannot issue credit note for a cancelled invoice.');

        $supplyType = $invoice->supply_type;
        $totals     = $this->calculateTotals($items, $supplyType);
        $number     = Sequence::generate($businessId, 'credit_note');

        $cn = CreditNoteTable::create([
            'business_id'    => $businessId,
            'created_by'     => $this->userId(),
            'invoice_id'     => (int)$input['invoice_id'],
            'client_id'      => $invoice->client_id,
            'number'         => $number,
            'reason'         => $input['reason'],
            'issue_date'     => $input['issue_date'],
            'supply_type'    => $supplyType,
            'place_of_supply'=> $invoice->place_of_supply,
            'subtotal'       => $totals['subtotal'],
            'cgst_total'     => $totals['cgst_total'],
            'sgst_total'     => $totals['sgst_total'],
            'igst_total'     => $totals['igst_total'],
            'tax_total'      => $totals['tax_total'],
            'total'          => $totals['total'],
            'status'         => 'draft',
            'notes'          => $input['notes'] ?? null,
        ]);

        $this->saveItems((int)$cn->id, $items, $supplyType);

        return $this->success([
            'credit_note_id' => $cn->id,
            'number'         => $number,
            'total'          => $totals['total'],
        ], 'Credit note created.');
    }

    // ── Issue (finalise) credit note ──────────────────────────────────────────

    public function issue(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $cn         = CreditNoteTable::find((int)$input['id']);

        if (!$cn || (int)$cn->business_id !== $businessId) $this->fail('Credit note not found.', 404);
        if ($cn->status !== 'draft') $this->fail('Only draft credit notes can be issued.');

        DB::statement(
            "UPDATE credit_notes SET status = 'issued' WHERE id = ?",
            [$cn->id]
        );

        return $this->success(['number' => $cn->number], 'Credit note issued.');
    }

    // ── Adjust against invoice balance ────────────────────────────────────────

    public function adjust(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $cn         = CreditNoteTable::find((int)$input['id']);

        if (!$cn || (int)$cn->business_id !== $businessId) $this->fail('Credit note not found.', 404);
        if ($cn->status !== 'issued') $this->fail('Credit note must be issued before adjustment.');

        $creditAmount = (float)$cn->total;
        $invoice      = DB::selectOne(
            "SELECT * FROM invoices WHERE id = ? LIMIT 1",
            [$cn->invoice_id]
        );

        if (!$invoice) $this->fail('Original invoice not found.', 404);

        $newDue    = max(0, round((float)$invoice->amount_due - $creditAmount, 2));
        $newPaid   = round((float)$invoice->total - $newDue, 2);
        $status    = $newDue <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : $invoice->status);

        DB::statement(
            "UPDATE invoices SET amount_due = ?, amount_paid = ?, status = ? WHERE id = ?",
            [$newDue, $newPaid, $status, $invoice->id]
        );
        DB::statement(
            "UPDATE credit_notes SET status = 'adjusted' WHERE id = ?",
            [$cn->id]
        );

        return $this->success(['invoice_balance' => $newDue], 'Credit note adjusted against invoice.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function calculateTotals(array $items, string $supplyType): array
    {
        $subtotal = $cgstTotal = $sgstTotal = $igstTotal = 0.0;

        foreach ($items as $item) {
            $qty     = (float)($item['quantity']   ?? 1);
            $price   = (float)($item['unit_price'] ?? 0);
            $gstRate = (float)($item['gst_rate']   ?? 0);
            $taxable = $qty * $price;
            $subtotal += $taxable;

            if ($supplyType === 'intra') {
                $cgstTotal += round($taxable * ($gstRate / 2 / 100), 2);
                $sgstTotal += round($taxable * ($gstRate / 2 / 100), 2);
            } else {
                $igstTotal += round($taxable * ($gstRate / 100), 2);
            }
        }

        $taxTotal = $cgstTotal + $sgstTotal + $igstTotal;
        $total    = round($subtotal + $taxTotal);

        return compact('subtotal', 'cgstTotal', 'sgstTotal', 'igstTotal', 'taxTotal', 'total') + [
            'cgst_total' => $cgstTotal, 'sgst_total' => $sgstTotal,
            'igst_total' => $igstTotal, 'tax_total'  => $taxTotal,
        ];
    }

    private function saveItems(int $cnId, array $items, string $supplyType): void
    {
        foreach ($items as $item) {
            $qty     = (float)($item['quantity']   ?? 1);
            $price   = (float)($item['unit_price'] ?? 0);
            $gstRate = (float)($item['gst_rate']   ?? 0);
            $taxable = $qty * $price;

            $cgstAmt = $sgstAmt = $igstAmt = 0.0;
            if ($supplyType === 'intra') {
                $cgstAmt = round($taxable * ($gstRate / 2 / 100), 2);
                $sgstAmt = round($taxable * ($gstRate / 2 / 100), 2);
            } else {
                $igstAmt = round($taxable * ($gstRate / 100), 2);
            }

            CreditNoteItem::create([
                'credit_note_id' => $cnId,
                'product_id'     => !empty($item['product_id']) ? (int)$item['product_id'] : null,
                'description'    => $item['description'],
                'hsn_sac'        => $item['hsn_sac'] ?? null,
                'unit'           => $item['unit']     ?? 'Nos',
                'quantity'       => $qty,
                'unit_price'     => $price,
                'taxable_amt'    => $taxable,
                'gst_rate'       => $gstRate,
                'cgst_amt'       => $cgstAmt,
                'sgst_amt'       => $sgstAmt,
                'igst_amt'       => $igstAmt,
                'total'          => $taxable + $cgstAmt + $sgstAmt + $igstAmt,
            ]);
        }
    }
}
