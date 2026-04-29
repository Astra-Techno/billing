<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Tables\Payment as PaymentTable;

class Payment extends Task
{
    // ── Record partial or full payment ────────────────────────────────────────

    public function record(array $input): array
    {
        $this->validate([
            'invoice_id'   => 'required|integer',
            'amount'       => 'required|numeric',
            'method'       => 'required|in:cash,upi,neft,rtgs,imps,cheque,card,netbanking,other',
            'payment_date' => 'required|date',
        ]);

        $businessId = $this->requireBusiness();
        $amount     = round((float)$input['amount'], 2);

        if ($amount <= 0) $this->fail('Payment amount must be greater than zero.');

        // Load invoice
        $invoice = DB::selectOne(
            "SELECT * FROM invoices WHERE id = ? AND business_id = ? LIMIT 1",
            [(int)$input['invoice_id'], $businessId]
        );
        if (!$invoice) $this->fail('Invoice not found.', 404);
        if ($invoice->status === 'cancelled') $this->fail('Cannot record payment on a cancelled invoice.');
        if ($invoice->status === 'paid') $this->fail('Invoice is already fully paid.');

        $amountDue = (float)$invoice->amount_due;
        if ($amount > $amountDue) {
            $this->fail("Payment amount (₹{$amount}) exceeds outstanding balance (₹{$amountDue}).");
        }

        // Create payment record
        PaymentTable::create([
            'business_id'  => $businessId,
            'invoice_id'   => (int)$input['invoice_id'],
            'client_id'    => $invoice->client_id,
            'recorded_by'  => $this->userId(),
            'amount'       => $amount,
            'method'       => $input['method'],
            'reference'    => $input['reference']   ?? null,
            'utr_number'   => $input['utr_number']  ?? null,
            'payment_date' => $input['payment_date'],
            'note'         => $input['note']        ?? null,
        ]);

        // Update invoice balances
        $newPaid = round((float)$invoice->amount_paid + $amount, 2);
        $newDue  = round((float)$invoice->total - $newPaid, 2);
        $status  = $newDue <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : $invoice->status);

        $paidAt = $newDue <= 0 ? 'paid_at = NOW(),' : '';
        DB::statement(
            "UPDATE invoices
             SET amount_paid = ?, amount_due = ?, status = ?, {$paidAt} updated_at = NOW()
             WHERE id = ?",
            [$newPaid, $newDue, $status, $invoice->id]
        );

        return $this->success([
            'amount_paid' => $newPaid,
            'amount_due'  => $newDue,
            'status'      => $status,
        ], 'Payment recorded.');
    }

    // ── Delete a payment (undo) ───────────────────────────────────────────────

    public function delete(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin', 'accountant']);

        $payment = PaymentTable::find((int)$input['id']);
        if (!$payment || (int)$payment->business_id !== $businessId)
            $this->fail('Payment not found.', 404);

        $invoiceId = $payment->invoice_id;
        $amount    = (float)$payment->amount;

        DB::statement("DELETE FROM payments WHERE id = ?", [$payment->id]);

        // Recalculate invoice
        $invoice = DB::selectOne(
            "SELECT total FROM invoices WHERE id = ? LIMIT 1",
            [$invoiceId]
        );
        $totalPaid = (float)(DB::selectOne(
            "SELECT COALESCE(SUM(amount),0) AS paid FROM payments WHERE invoice_id = ?",
            [$invoiceId]
        )?->paid ?? 0);

        $due    = round((float)$invoice->total - $totalPaid, 2);
        $status = $due <= 0 ? 'paid' : ($totalPaid > 0 ? 'partial' : 'sent');

        DB::statement(
            "UPDATE invoices SET amount_paid = ?, amount_due = ?, status = ?, paid_at = NULL WHERE id = ?",
            [$totalPaid, $due, $status, $invoiceId]
        );

        return $this->success(null, 'Payment deleted and invoice balance updated.');
    }
}
