<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Tables\Quote as QuoteTable;
use App\Tables\QuoteItem;

class Quote extends Task
{
    protected bool $useTransaction = true;

    // ── Create quote / proforma ───────────────────────────────────────────────

    public function create(array $input): array
    {
        $this->validate([
            'client_id'  => 'required|integer',
            'issue_date' => 'required|date',
            'items'      => 'required',
        ]);

        $businessId = $this->requireBusiness();
        $this->validateItems($input['items'] ?? []);

        $supplyType = $this->resolveSupplyType($businessId, (int)$input['client_id'], $input);
        $totals     = $this->calculateTotals($input['items'], $supplyType);
        $type       = $input['type'] ?? 'quote';
        $number     = Sequence::generate($businessId, 'quote');
        $fy         = Sequence::currentFinancialYear();

        $quote = QuoteTable::create([
            'business_id'    => $businessId,
            'created_by'     => $this->userId(),
            'client_id'      => (int)$input['client_id'],
            'number'         => $number,
            'type'           => $type,
            'status'         => 'draft',
            'issue_date'     => $input['issue_date'],
            'valid_until'    => $input['valid_until'] ?? date('Y-m-d', strtotime('+30 days')),
            'financial_year' => $fy,
            'supply_type'    => $supplyType,
            'place_of_supply'=> $input['place_of_supply'] ?? null,
            'subtotal'       => $totals['subtotal'],
            'cgst_total'     => $totals['cgst_total'],
            'sgst_total'     => $totals['sgst_total'],
            'igst_total'     => $totals['igst_total'],
            'utgst_total'    => $totals['utgst_total'],
            'tax_total'      => $totals['tax_total'],
            'discount'       => $totals['discount'],
            'total'          => $totals['total'],
            'notes'          => $input['notes'] ?? null,
            'terms'          => $input['terms'] ?? null,
        ]);

        $this->saveItems((int)$quote->id, $input['items'], $supplyType);

        return $this->success([
            'quote_id' => $quote->id,
            'number'   => $number,
            'total'    => $totals['total'],
        ], ucfirst($type) . ' created.');
    }

    // ── Update quote (draft only) ─────────────────────────────────────────────

    public function update(array $input): array
    {
        $this->validate([
            'id'        => 'required|integer',
            'client_id' => 'required|integer',
            'items'     => 'required',
        ]);

        $businessId = $this->requireBusiness();
        $quote      = $this->findQuote((int)$input['id'], $businessId);

        if ($quote->status !== 'draft')
            $this->fail('Only draft quotes can be edited.');

        $this->validateItems($input['items'] ?? []);
        $supplyType = $this->resolveSupplyType($businessId, (int)$input['client_id'], $input);
        $totals     = $this->calculateTotals($input['items'], $supplyType);

        $quote->fill([
            'client_id'      => (int)$input['client_id'],
            'issue_date'     => $input['issue_date']     ?? $quote->issue_date,
            'valid_until'    => $input['valid_until']    ?? $quote->valid_until,
            'supply_type'    => $supplyType,
            'place_of_supply'=> $input['place_of_supply'] ?? $quote->place_of_supply,
            'subtotal'       => $totals['subtotal'],
            'cgst_total'     => $totals['cgst_total'],
            'sgst_total'     => $totals['sgst_total'],
            'igst_total'     => $totals['igst_total'],
            'utgst_total'    => $totals['utgst_total'],
            'tax_total'      => $totals['tax_total'],
            'discount'       => $totals['discount'],
            'total'          => $totals['total'],
            'notes'          => $input['notes']          ?? $quote->notes,
            'terms'          => $input['terms']          ?? $quote->terms,
        ]);
        $quote->save();

        DB::statement("DELETE FROM quote_items WHERE quote_id = ?", [$quote->id]);
        $this->saveItems((int)$quote->id, $input['items'], $supplyType);

        return $this->success(['quote_id' => $quote->id], 'Quote updated.');
    }

    // ── Mark sent ─────────────────────────────────────────────────────────────

    public function markSent(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $quote      = $this->findQuote((int)$input['id'], $businessId);

        DB::statement(
            "UPDATE quotes SET status = 'sent', sent_at = NOW() WHERE id = ?",
            [$quote->id]
        );

        return $this->success(null, 'Quote marked as sent.');
    }

    // ── Accept / Decline ──────────────────────────────────────────────────────

    public function accept(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $quote      = $this->findQuote((int)$input['id'], $businessId);

        DB::statement(
            "UPDATE quotes SET status = 'accepted', accepted_at = NOW() WHERE id = ?",
            [$quote->id]
        );

        return $this->success(null, 'Quote accepted.');
    }

    public function decline(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $quote      = $this->findQuote((int)$input['id'], $businessId);

        DB::statement(
            "UPDATE quotes SET status = 'declined' WHERE id = ?",
            [$quote->id]
        );

        return $this->success(null, 'Quote declined.');
    }

    // ── Convert to invoice ────────────────────────────────────────────────────

    public function convertToInvoice(array $input): array
    {
        $this->validate([
            'id'       => 'required|integer',
            'due_date' => 'required|date',
        ]);

        $businessId = $this->requireBusiness();
        $quote      = $this->findQuote((int)$input['id'], $businessId);

        if (!in_array($quote->status, ['accepted', 'sent']))
            $this->fail('Only accepted or sent quotes can be converted to invoices.');

        // Load quote items
        $items = DB::select(
            "SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order",
            [$quote->id]
        );

        $itemsPayload = [];
        foreach ($items as $item) {
            $itemsPayload[] = [
                'product_id'   => $item->product_id,
                'description'  => $item->description,
                'hsn_sac'      => $item->hsn_sac,
                'unit'         => $item->unit,
                'quantity'     => $item->quantity,
                'unit_price'   => $item->unit_price,
                'discount_pct' => $item->discount_pct,
                'gst_rate'     => $item->gst_rate,
                'sort_order'   => $item->sort_order,
            ];
        }

        $result = Task::run('Invoice.create', [
            'client_id'      => $quote->client_id,
            'quote_id'       => $quote->id,
            'invoice_type'   => 'tax_invoice',
            'issue_date'     => date('Y-m-d'),
            'due_date'       => $input['due_date'],
            'supply_type'    => $quote->supply_type,
            'place_of_supply'=> $quote->place_of_supply,
            'items'          => $itemsPayload,
            'notes'          => $quote->notes,
            'terms'          => $quote->terms,
        ]);

        // Mark quote as converted
        DB::statement(
            "UPDATE quotes SET status = 'converted', converted_at = NOW() WHERE id = ?",
            [$quote->id]
        );

        return $this->success($result['data'] ?? [], 'Quote converted to invoice.');
    }

    // ── Delete draft quote ────────────────────────────────────────────────────

    public function delete(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $quote      = $this->findQuote((int)$input['id'], $businessId);

        if ($quote->status !== 'draft')
            $this->fail('Only draft quotes can be deleted.');

        DB::statement("DELETE FROM quote_items WHERE quote_id = ?", [$quote->id]);
        DB::statement("DELETE FROM quotes WHERE id = ?", [$quote->id]);

        return $this->success(null, 'Quote deleted.');
    }

    // ── Private helpers (same GST logic as Invoice) ───────────────────────────

    private function findQuote(int $id, int $businessId): object
    {
        $quote = QuoteTable::find($id);
        if (!$quote || (int)$quote->business_id !== $businessId)
            $this->fail('Quote not found.', 404);
        return $quote;
    }

    private function validateItems(array $items): void
    {
        if (empty($items)) $this->fail('At least one item is required.');
        foreach ($items as $i => $item) {
            if (empty($item['description']))   $this->fail("Item " . ($i + 1) . ": description is required.");
            if ((float)($item['quantity'] ?? 0) <= 0) $this->fail("Item " . ($i + 1) . ": quantity must be > 0.");
        }
    }

    private function resolveSupplyType(int $businessId, int $clientId, array $input): string
    {
        if (!empty($input['supply_type'])) return $input['supply_type'];

        $business = DB::selectOne("SELECT state_id FROM businesses WHERE id = ? LIMIT 1", [$businessId]);
        $client   = DB::selectOne("SELECT state_id FROM clients WHERE id = ? LIMIT 1", [$clientId]);

        if (!$business || !$client) return 'intra';
        return ($business->state_id == $client->state_id) ? 'intra' : 'inter';
    }

    private function calculateTotals(array $items, string $supplyType): array
    {
        $subtotal = $cgstTotal = $sgstTotal = $igstTotal = $utgstTotal = $discount = 0.0;

        foreach ($items as $item) {
            $qty     = (float)($item['quantity']    ?? 1);
            $price   = (float)($item['unit_price']  ?? 0);
            $discPct = (float)($item['discount_pct']?? 0);
            $gstRate = (float)($item['gst_rate']    ?? 0);
            $taxable = ($qty * $price) - round(($qty * $price) * ($discPct / 100), 2);
            $discount += round(($qty * $price) * ($discPct / 100), 2);
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

        return compact('subtotal', 'cgstTotal', 'sgstTotal', 'igstTotal', 'utgstTotal', 'taxTotal', 'discount', 'total') + [
            'cgst_total' => $cgstTotal, 'sgst_total' => $sgstTotal,
            'igst_total' => $igstTotal, 'utgst_total' => $utgstTotal,
            'tax_total'  => $taxTotal,
        ];
    }

    private function saveItems(int $quoteId, array $items, string $supplyType): void
    {
        foreach ($items as $i => $item) {
            $qty     = (float)($item['quantity']    ?? 1);
            $price   = (float)($item['unit_price']  ?? 0);
            $discPct = (float)($item['discount_pct']?? 0);
            $gstRate = (float)($item['gst_rate']    ?? 0);
            $discAmt = round($qty * $price * ($discPct / 100), 2);
            $taxable = $qty * $price - $discAmt;

            $cgstRate = $sgstRate = $igstRate = 0.0;
            $cgstAmt  = $sgstAmt  = $igstAmt  = 0.0;

            if ($supplyType === 'intra') {
                $cgstRate = $sgstRate = $gstRate / 2;
                $cgstAmt  = round($taxable * ($cgstRate / 100), 2);
                $sgstAmt  = round($taxable * ($sgstRate / 100), 2);
            } else {
                $igstRate = $gstRate;
                $igstAmt  = round($taxable * ($gstRate / 100), 2);
            }

            QuoteItem::create([
                'quote_id'     => $quoteId,
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
                'cgst_amt'     => $cgstAmt,
                'sgst_amt'     => $sgstAmt,
                'igst_amt'     => $igstAmt,
                'total'        => $taxable + $cgstAmt + $sgstAmt + $igstAmt,
                'sort_order'   => $i,
            ]);
        }
    }
}
