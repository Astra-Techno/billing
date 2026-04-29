<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Tables\Invoice as InvoiceTable;
use App\Tables\InvoiceItem;

class Invoice extends Task
{
    protected bool $useTransaction = true;

    // ── Create invoice ────────────────────────────────────────────────────────

    public function create(array $input): array
    {
        $this->validate([
            'client_id'    => 'required|integer',
            'issue_date'   => 'required|date',
            'due_date'     => 'required|date',
            'items'        => 'required',
        ]);

        $businessId = $this->requireBusiness();
        $this->validateItems($input['items'] ?? []);

        // Determine supply type (intra-state = CGST+SGST, inter-state = IGST)
        $supplyType = $this->resolveSupplyType($businessId, (int)$input['client_id'], $input);

        // Generate invoice number
        $number = Sequence::generate($businessId, 'invoice');
        $fy     = Sequence::currentFinancialYear();

        // Calculate totals
        $totals = $this->calculateTotals($input['items'], $supplyType);

        $invoice = InvoiceTable::create([
            'business_id'   => $businessId,
            'created_by'    => $this->userId(),
            'client_id'     => (int)$input['client_id'],
            'quote_id'      => !empty($input['quote_id']) ? (int)$input['quote_id'] : null,
            'number'        => $number,
            'invoice_type'  => $input['invoice_type']  ?? 'tax_invoice',
            'status'        => 'draft',
            'issue_date'    => $input['issue_date'],
            'due_date'      => $input['due_date'],
            'financial_year'=> $fy,
            'supply_type'   => $supplyType,
            'place_of_supply' => $input['place_of_supply'] ?? null,
            'reverse_charge'=> !empty($input['reverse_charge']) ? 1 : 0,
            'subtotal'      => $totals['subtotal'],
            'cgst_total'    => $totals['cgst_total'],
            'sgst_total'    => $totals['sgst_total'],
            'igst_total'    => $totals['igst_total'],
            'utgst_total'   => $totals['utgst_total'],
            'tax_total'     => $totals['tax_total'],
            'discount'      => $totals['discount'],
            'round_off'     => $totals['round_off'],
            'total'         => $totals['total'],
            'amount_paid'   => 0,
            'amount_due'    => $totals['total'],
            'is_recurring'  => !empty($input['is_recurring']) ? 1 : 0,
            'recur_every'   => $input['recur_every']   ?? null,
            'recur_period'  => $input['recur_period']  ?? null,
            'recur_ends_at' => $input['recur_ends_at'] ?? null,
            'notes'         => $input['notes']         ?? null,
            'terms'         => $input['terms']         ?? null,
        ]);

        // Set next recur date
        if (!empty($input['is_recurring'])) {
            $this->setNextRecurDate((int)$invoice->id, $input['issue_date'], $input);
        }

        // Save line items
        $this->saveItems((int)$invoice->id, $input['items'], $supplyType);

        // Track usage
        $this->trackUsage($businessId, 'invoices_created');

        return $this->success([
            'invoice_id' => $invoice->id,
            'number'     => $number,
            'total'      => $totals['total'],
        ], 'Invoice created.');
    }

    // ── Update invoice (only draft) ───────────────────────────────────────────

    public function update(array $input): array
    {
        $this->validate([
            'id'        => 'required|integer',
            'client_id' => 'required|integer',
            'items'     => 'required',
        ]);

        $businessId = $this->requireBusiness();
        $invoice    = $this->findInvoice((int)$input['id'], $businessId);

        if ($invoice->status !== 'draft')
            $this->fail('Only draft invoices can be edited. Cancel and duplicate if needed.');

        $this->validateItems($input['items'] ?? []);
        $supplyType = $this->resolveSupplyType($businessId, (int)$input['client_id'], $input);
        $totals     = $this->calculateTotals($input['items'], $supplyType);

        $invoice->fill([
            'client_id'      => (int)$input['client_id'],
            'invoice_type'   => $input['invoice_type']    ?? $invoice->invoice_type,
            'issue_date'     => $input['issue_date']      ?? $invoice->issue_date,
            'due_date'       => $input['due_date']        ?? $invoice->due_date,
            'supply_type'    => $supplyType,
            'place_of_supply'=> $input['place_of_supply'] ?? $invoice->place_of_supply,
            'reverse_charge' => !empty($input['reverse_charge']) ? 1 : 0,
            'subtotal'       => $totals['subtotal'],
            'cgst_total'     => $totals['cgst_total'],
            'sgst_total'     => $totals['sgst_total'],
            'igst_total'     => $totals['igst_total'],
            'utgst_total'    => $totals['utgst_total'],
            'tax_total'      => $totals['tax_total'],
            'discount'       => $totals['discount'],
            'round_off'      => $totals['round_off'],
            'total'          => $totals['total'],
            'amount_due'     => $totals['total'],
            'notes'          => $input['notes']           ?? $invoice->notes,
            'terms'          => $input['terms']           ?? $invoice->terms,
        ]);
        $invoice->save();

        // Replace items
        DB::statement("DELETE FROM invoice_items WHERE invoice_id = ?", [$invoice->id]);
        $this->saveItems((int)$invoice->id, $input['items'], $supplyType);

        return $this->success(['invoice_id' => $invoice->id], 'Invoice updated.');
    }

    // ── Mark as sent ──────────────────────────────────────────────────────────

    public function markSent(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $invoice    = $this->findInvoice((int)$input['id'], $businessId);

        if ($invoice->status === 'cancelled')
            $this->fail('Cannot send a cancelled invoice.');

        DB::statement(
            "UPDATE invoices SET status = 'sent', sent_at = NOW() WHERE id = ?",
            [$invoice->id]
        );

        return $this->success(['invoice_id' => $invoice->id], 'Invoice marked as sent.');
    }

    // ── Record payment (shortcut — full payment) ──────────────────────────────

    public function markPaid(array $input): array
    {
        $this->validate([
            'id'           => 'required|integer',
            'payment_date' => 'required|date',
            'method'       => 'required|in:cash,upi,neft,rtgs,imps,cheque,card,netbanking,other',
        ]);

        $businessId = $this->requireBusiness();
        $invoice    = $this->findInvoice((int)$input['id'], $businessId);

        if ($invoice->status === 'cancelled')
            $this->fail('Cannot mark a cancelled invoice as paid.');
        if ($invoice->status === 'paid')
            $this->fail('Invoice is already fully paid.');

        $amountDue = (float)$invoice->amount_due;

        // Record payment
        DB::statement(
            "INSERT INTO payments
                (business_id, invoice_id, client_id, recorded_by, amount, method, reference, utr_number, payment_date, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $businessId,
                $invoice->id,
                $invoice->client_id,
                $this->userId(),
                $amountDue,
                $input['method'],
                $input['reference']  ?? null,
                $input['utr_number'] ?? null,
                $input['payment_date'],
            ]
        );

        // Update invoice
        DB::statement(
            "UPDATE invoices
             SET amount_paid = total, amount_due = 0,
                 status = 'paid', paid_at = NOW()
             WHERE id = ?",
            [$invoice->id]
        );

        return $this->success(['invoice_id' => $invoice->id], 'Invoice marked as paid.');
    }

    // ── Cancel invoice ────────────────────────────────────────────────────────

    public function cancel(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $invoice    = $this->findInvoice((int)$input['id'], $businessId);

        if ($invoice->status === 'cancelled') $this->fail('Already cancelled.');
        if ($invoice->status === 'paid')      $this->fail('Cannot cancel a paid invoice. Issue a credit note instead.');

        DB::statement(
            "UPDATE invoices SET status = 'cancelled' WHERE id = ?",
            [$invoice->id]
        );

        return $this->success(null, 'Invoice cancelled.');
    }

    // ── Duplicate invoice ─────────────────────────────────────────────────────

    public function duplicate(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $original   = $this->findInvoice((int)$input['id'], $businessId);

        $number  = Sequence::generate($businessId, 'invoice');
        $fy      = Sequence::currentFinancialYear();
        $today   = date('Y-m-d');

        // Clone invoice
        $newInvoice = InvoiceTable::create([
            'business_id'    => $businessId,
            'created_by'     => $this->userId(),
            'client_id'      => $original->client_id,
            'number'         => $number,
            'invoice_type'   => $original->invoice_type,
            'status'         => 'draft',
            'issue_date'     => $today,
            'due_date'       => date('Y-m-d', strtotime($today . " +{$original->credit_days} days")),
            'financial_year' => $fy,
            'supply_type'    => $original->supply_type,
            'place_of_supply'=> $original->place_of_supply,
            'reverse_charge' => $original->reverse_charge,
            'subtotal'       => $original->subtotal,
            'cgst_total'     => $original->cgst_total,
            'sgst_total'     => $original->sgst_total,
            'igst_total'     => $original->igst_total,
            'utgst_total'    => $original->utgst_total,
            'tax_total'      => $original->tax_total,
            'discount'       => $original->discount,
            'round_off'      => $original->round_off,
            'total'          => $original->total,
            'amount_paid'    => 0,
            'amount_due'     => $original->total,
            'notes'          => $original->notes,
            'terms'          => $original->terms,
        ]);

        // Clone items
        $items = DB::select(
            "SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order",
            [$original->id]
        );
        foreach ($items as $item) {
            InvoiceItem::create([
                'invoice_id'   => $newInvoice->id,
                'product_id'   => $item->product_id,
                'description'  => $item->description,
                'hsn_sac'      => $item->hsn_sac,
                'unit'         => $item->unit,
                'quantity'     => $item->quantity,
                'unit_price'   => $item->unit_price,
                'discount_pct' => $item->discount_pct,
                'discount_amt' => $item->discount_amt,
                'taxable_amt'  => $item->taxable_amt,
                'gst_rate'     => $item->gst_rate,
                'cgst_rate'    => $item->cgst_rate,
                'sgst_rate'    => $item->sgst_rate,
                'igst_rate'    => $item->igst_rate,
                'utgst_rate'   => $item->utgst_rate,
                'cgst_amt'     => $item->cgst_amt,
                'sgst_amt'     => $item->sgst_amt,
                'igst_amt'     => $item->igst_amt,
                'utgst_amt'    => $item->utgst_amt,
                'total'        => $item->total,
                'sort_order'   => $item->sort_order,
            ]);
        }

        return $this->success([
            'invoice_id' => $newInvoice->id,
            'number'     => $number,
        ], 'Invoice duplicated as draft.');
    }

    // ── Generate recurring invoice ────────────────────────────────────────────

    public function generateRecurring(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $template   = $this->findInvoice((int)$input['id'], $businessId);

        if (!$template->is_recurring) $this->fail('This is not a recurring invoice.');

        $result = $this->duplicate(['id' => $template->id]);
        $newId  = $result['data']['invoice_id'];

        // Update next recur date on template
        $nextDate = $this->computeNextDate(
            $template->next_recur_date ?? date('Y-m-d'),
            (int)$template->recur_every,
            $template->recur_period
        );
        DB::statement(
            "UPDATE invoices SET next_recur_date = ? WHERE id = ?",
            [$nextDate, $template->id]
        );

        return $this->success(['invoice_id' => $newId, 'next_recur_date' => $nextDate], 'Recurring invoice generated.');
    }

    // ── Update overdue statuses (run via cron) ────────────────────────────────

    public function updateOverdue(array $input): array
    {
        DB::statement(
            "UPDATE invoices
             SET status = 'overdue'
             WHERE status IN ('sent','partial')
               AND due_date < CURDATE()"
        );

        return $this->success(null, 'Overdue statuses updated.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function findInvoice(int $id, int $businessId): object
    {
        $invoice = InvoiceTable::find($id);
        if (!$invoice || (int)$invoice->business_id !== $businessId)
            $this->fail('Invoice not found.', 404);
        return $invoice;
    }

    private function validateItems(array $items): void
    {
        if (empty($items)) $this->fail('At least one item is required.');
        foreach ($items as $i => $item) {
            if (empty($item['description'])) $this->fail("Item " . ($i + 1) . ": description is required.");
            if (!isset($item['quantity']) || (float)$item['quantity'] <= 0) $this->fail("Item " . ($i + 1) . ": quantity must be > 0.");
            if (!isset($item['unit_price'])) $this->fail("Item " . ($i + 1) . ": unit price is required.");
        }
    }

    /**
     * Determine intra vs inter-state supply.
     * Intra-state → CGST + SGST
     * Inter-state → IGST
     * Uses business state vs client state.
     */
    private function resolveSupplyType(int $businessId, int $clientId, array $input): string
    {
        // Allow explicit override
        if (!empty($input['supply_type'])) return $input['supply_type'];

        $business = DB::selectOne(
            "SELECT state_id FROM businesses WHERE id = ? LIMIT 1",
            [$businessId]
        );
        $client = DB::selectOne(
            "SELECT state_id FROM clients WHERE id = ? LIMIT 1",
            [$clientId]
        );

        if (!$business || !$client) return 'intra';

        return ($business->state_id == $client->state_id) ? 'intra' : 'inter';
    }

    /**
     * Calculate all GST totals from items array.
     */
    private function calculateTotals(array $items, string $supplyType): array
    {
        $subtotal   = 0;
        $cgstTotal  = 0;
        $sgstTotal  = 0;
        $igstTotal  = 0;
        $utgstTotal = 0;
        $discount   = 0;

        foreach ($items as $item) {
            $qty          = (float)($item['quantity']   ?? 1);
            $price        = (float)($item['unit_price'] ?? 0);
            $discPct      = (float)($item['discount_pct'] ?? 0);
            $lineTotal    = $qty * $price;
            $discAmt      = round($lineTotal * ($discPct / 100), 2);
            $taxable      = $lineTotal - $discAmt;
            $gstRate      = (float)($item['gst_rate'] ?? 0);

            $discount  += $discAmt;
            $subtotal  += $taxable;

            if ($supplyType === 'intra') {
                $cgstAmt   = round($taxable * ($gstRate / 2 / 100), 2);
                $sgstAmt   = round($taxable * ($gstRate / 2 / 100), 2);
                $cgstTotal += $cgstAmt;
                $sgstTotal += $sgstAmt;
            } elseif ($supplyType === 'inter') {
                $igstAmt   = round($taxable * ($gstRate / 100), 2);
                $igstTotal += $igstAmt;
            }
        }

        $taxTotal = $cgstTotal + $sgstTotal + $igstTotal + $utgstTotal;
        $rawTotal = $subtotal + $taxTotal;
        $total    = round($rawTotal);
        $roundOff = round($total - $rawTotal, 2);

        return compact('subtotal', 'cgstTotal', 'sgstTotal', 'igstTotal', 'utgstTotal', 'taxTotal', 'discount', 'total', 'roundOff') + [
            'cgst_total'  => $cgstTotal,
            'sgst_total'  => $sgstTotal,
            'igst_total'  => $igstTotal,
            'utgst_total' => $utgstTotal,
            'tax_total'   => $taxTotal,
            'round_off'   => $roundOff,
        ];
    }

    private function saveItems(int $invoiceId, array $items, string $supplyType): void
    {
        foreach ($items as $i => $item) {
            $qty     = (float)($item['quantity']    ?? 1);
            $price   = (float)($item['unit_price']  ?? 0);
            $discPct = (float)($item['discount_pct']?? 0);
            $gstRate = (float)($item['gst_rate']    ?? 0);

            $lineTotal  = $qty * $price;
            $discAmt    = round($lineTotal * ($discPct / 100), 2);
            $taxable    = $lineTotal - $discAmt;

            $cgstRate = $sgstRate = $igstRate = $utgstRate = 0.0;
            $cgstAmt  = $sgstAmt  = $igstAmt  = $utgstAmt  = 0.0;

            if ($supplyType === 'intra') {
                $cgstRate = $sgstRate = $gstRate / 2;
                $cgstAmt  = round($taxable * ($cgstRate / 100), 2);
                $sgstAmt  = round($taxable * ($sgstRate / 100), 2);
            } elseif ($supplyType === 'inter') {
                $igstRate = $gstRate;
                $igstAmt  = round($taxable * ($igstRate / 100), 2);
            }

            $total = $taxable + $cgstAmt + $sgstAmt + $igstAmt + $utgstAmt;

            InvoiceItem::create([
                'invoice_id'   => $invoiceId,
                'product_id'   => !empty($item['product_id']) ? (int)$item['product_id'] : null,
                'description'  => $item['description'],
                'hsn_sac'      => $item['hsn_sac'] ?? null,
                'unit'         => $item['unit']     ?? 'Nos',
                'quantity'     => $qty,
                'unit_price'   => $price,
                'discount_pct' => $discPct,
                'discount_amt' => $discAmt,
                'taxable_amt'  => $taxable,
                'gst_rate'     => $gstRate,
                'cgst_rate'    => $cgstRate,
                'sgst_rate'    => $sgstRate,
                'igst_rate'    => $igstRate,
                'utgst_rate'   => $utgstRate,
                'cgst_amt'     => $cgstAmt,
                'sgst_amt'     => $sgstAmt,
                'igst_amt'     => $igstAmt,
                'utgst_amt'    => $utgstAmt,
                'total'        => $total,
                'sort_order'   => $i,
            ]);
        }
    }

    private function setNextRecurDate(int $invoiceId, string $fromDate, array $input): void
    {
        $nextDate = $this->computeNextDate(
            $fromDate,
            (int)($input['recur_every'] ?? 1),
            $input['recur_period'] ?? 'month'
        );
        DB::statement(
            "UPDATE invoices SET next_recur_date = ? WHERE id = ?",
            [$nextDate, $invoiceId]
        );
    }

    private function computeNextDate(string $fromDate, int $every, string $period): string
    {
        $map = ['week' => 'week', 'month' => 'month', 'quarter' => 'month', 'year' => 'year'];
        $mul = $period === 'quarter' ? $every * 3 : $every;
        return date('Y-m-d', strtotime("{$fromDate} +{$mul} {$map[$period]}"));
    }

    private function trackUsage(int $businessId, string $metric): void
    {
        $period = date('Y-m');
        DB::statement(
            "INSERT INTO usage_logs (business_id, metric, period, value)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE value = value + 1",
            [$businessId, $metric, $period]
        );
    }
}
