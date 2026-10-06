<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Core\InventoryStock;
use App\Tables\PurchaseInvoice as PITable;
use App\Tables\PurchaseInvoiceItem;
use App\Tables\PurchasePayment;

class PurchaseInvoice extends Task
{
    protected bool $useTransaction = true;

    // ── Create ──────────────────────────────────────────────────────────────

    public function create(array $input): array
    {
        $this->validate([
            'supplier_id'  => 'required|integer',
            'invoice_date' => 'required|date',
            'items'        => 'required',
        ]);

        $businessId = $this->requireBusiness();
        $this->requirePermission('purchases', 'create');
        $locationId = (int)($input['location_id'] ?? InventoryStock::defaultLocation($businessId));
        $this->validateItems($input['items'] ?? []);

        $supplyType = $this->resolveSupplyType($businessId, (int)$input['supplier_id'], $locationId);
        $fy     = Sequence::financialYearForDate((string)$input['invoice_date']);
        $number = Sequence::generate($businessId, 'pi', $fy);
        $totals = $this->calculateTotals($input['items'], $supplyType);

        $pi = PITable::create([
            'business_id'    => $businessId,
            'created_by'     => $this->userId(),
            'supplier_id'    => (int)$input['supplier_id'],
            'po_id'          => !empty($input['po_id']) ? (int)$input['po_id'] : null,
            'location_id'    => $locationId,
            'number'         => $number,
            'supplier_inv_no'=> trim($input['supplier_inv_no'] ?? '') ?: null,
            'status'         => 'draft',
            'invoice_date'   => $input['invoice_date'],
            'due_date'       => $input['due_date'] ?? null,
            'financial_year' => $fy,
            'supply_type'    => $supplyType,
            'place_of_supply'=> $input['place_of_supply'] ?? null,
            'reverse_charge' => !empty($input['reverse_charge']) ? 1 : 0,
            'subtotal'       => $totals['subtotal'],
            'cgst_total'     => $totals['cgst_total'],
            'sgst_total'     => $totals['sgst_total'],
            'igst_total'     => $totals['igst_total'],
            'tax_total'      => $totals['tax_total'],
            'discount'       => $totals['discount'],
            'round_off'      => $totals['round_off'],
            'total'          => $totals['total'],
            'amount_paid'    => 0,
            'amount_due'     => $totals['total'],
            'notes'          => $input['notes'] ?? null,
        ]);

        $this->saveItems((int)$pi->id, $input['items'], $supplyType);

        return $this->success([
            'id'     => $pi->id,
            'number' => $number,
            'total'  => $totals['total'],
        ], 'Purchase invoice created.');
    }

    // ── Update (draft only) ─────────────────────────────────────────────────

    public function update(array $input): array
    {
        $this->validate([
            'id'           => 'required|integer',
            'supplier_id'  => 'required|integer',
            'items'        => 'required',
        ]);

        $businessId = $this->requireBusiness();
        $this->requirePermission('purchases', 'edit');
        $pi = $this->findPI((int)$input['id'], $businessId);

        if ($pi->status !== 'draft')
            $this->fail('Only draft purchase invoices can be edited.');

        $this->validateItems($input['items'] ?? []);
        $locationId = (int)($input['location_id'] ?? $pi->location_id ?? InventoryStock::defaultLocation($businessId));
        $supplyType = $this->resolveSupplyType($businessId, (int)$input['supplier_id'], $locationId);
        $totals = $this->calculateTotals($input['items'], $supplyType);

        $pi->fill([
            'supplier_id'    => (int)$input['supplier_id'],
            'po_id'          => !empty($input['po_id']) ? (int)$input['po_id'] : null,
            'location_id'    => $locationId,
            'supplier_inv_no'=> trim($input['supplier_inv_no'] ?? '') ?: null,
            'invoice_date'   => $input['invoice_date'] ?? $pi->invoice_date,
            'due_date'       => $input['due_date'] ?? $pi->due_date,
            'supply_type'    => $supplyType,
            'place_of_supply'=> $input['place_of_supply'] ?? $pi->place_of_supply,
            'reverse_charge' => !empty($input['reverse_charge']) ? 1 : 0,
            'subtotal'       => $totals['subtotal'],
            'cgst_total'     => $totals['cgst_total'],
            'sgst_total'     => $totals['sgst_total'],
            'igst_total'     => $totals['igst_total'],
            'tax_total'      => $totals['tax_total'],
            'discount'       => $totals['discount'],
            'round_off'      => $totals['round_off'],
            'total'          => $totals['total'],
            'amount_due'     => $totals['total'] - (float)$pi->amount_paid,
            'notes'          => $input['notes'] ?? $pi->notes,
        ]);
        $pi->save();

        DB::statement("DELETE FROM purchase_invoice_items WHERE pi_id = ?", [$pi->id]);
        $this->saveItems((int)$pi->id, $input['items'], $supplyType);

        return $this->success(['id' => $pi->id], 'Purchase invoice updated.');
    }

    // ── Record (finalize draft → recorded, post stock) ──────────────────────

    public function record(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->requirePermission('purchases', 'edit');
        $pi = $this->findPI((int)$input['id'], $businessId);

        if ($pi->status !== 'draft')
            $this->fail('Only draft purchase invoices can be recorded.');

        DB::statement(
            "UPDATE purchase_invoices SET status = 'recorded' WHERE id = ?",
            [$pi->id]
        );

        // Post stock inward
        $locId = (int)($pi->location_id ?: InventoryStock::defaultLocation($businessId));
        InventoryStock::postDocument($businessId, $locId, 'purchase_invoice', (int)$pi->id, 'purchase_invoice_items', 'pi_id', 'purchase', 1, $this->userId());

        return $this->success(null, 'Purchase invoice recorded and stock updated.');
    }

    // ── Pay (record payment against purchase invoice) ───────────────────────

    public function pay(array $input): array
    {
        $this->validate([
            'id'           => 'required|integer',
            'amount'       => 'required|numeric',
            'method'       => 'required',
            'payment_date' => 'required|date',
        ]);

        $businessId = $this->requireBusiness();
        $this->requirePermission('purchases', 'edit');
        $pi = $this->findPI((int)$input['id'], $businessId);

        if (in_array($pi->status, ['draft', 'cancelled']))
            $this->fail('Cannot pay a ' . $pi->status . ' purchase invoice.');

        $amount = round((float)$input['amount'], 2);
        if ($amount <= 0) $this->fail('Payment amount must be positive.');

        PurchasePayment::create([
            'business_id'  => $businessId,
            'pi_id'        => (int)$pi->id,
            'supplier_id'  => (int)$pi->supplier_id,
            'recorded_by'  => $this->userId(),
            'amount'       => $amount,
            'method'       => $input['method'],
            'reference'    => $input['reference'] ?? null,
            'utr_number'   => $input['utr_number'] ?? null,
            'payment_date' => $input['payment_date'],
            'note'         => $input['note'] ?? null,
        ]);

        // Recalculate
        $paid = (float)DB::selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total_paid FROM purchase_payments WHERE pi_id = ?",
            [$pi->id]
        )->total_paid;

        $due    = round((float)$pi->total - $paid, 2);
        $status = $due <= 0 ? 'paid' : 'partial';

        DB::statement(
            "UPDATE purchase_invoices SET amount_paid = ?, amount_due = ?, status = ? WHERE id = ?",
            [$paid, max(0, $due), $status, $pi->id]
        );

        return $this->success(['amount_due' => max(0, $due)], 'Payment recorded.');
    }

    // ── Cancel ──────────────────────────────────────────────────────────────

    public function cancel(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->requirePermission('purchases', 'edit');
        $pi = $this->findPI((int)$input['id'], $businessId);

        if ($pi->status === 'cancelled')
            $this->fail('Already cancelled.');

        // Reverse stock if it was recorded
        if (in_array($pi->status, ['recorded', 'paid', 'partial'])) {
            $locId = (int)($pi->location_id ?: InventoryStock::defaultLocation($businessId));
            InventoryStock::postDocument($businessId, $locId, 'purchase_invoice', (int)$pi->id, 'purchase_invoice_items', 'pi_id', 'purchase', -1, $this->userId());
        }

        DB::statement(
            "UPDATE purchase_invoices SET status = 'cancelled' WHERE id = ?",
            [$pi->id]
        );

        DB::statement(
            "INSERT INTO audit_log (business_id, user_id, action, entity_type, entity_id, snapshot, note) VALUES (?, ?, 'cancel', 'purchase_invoice', ?, ?, ?)",
            [$businessId, $this->userId(), $pi->id, json_encode(['number' => $pi->number, 'total' => $pi->total]), "Purchase invoice {$pi->number} cancelled"]
        );

        return $this->success(null, 'Purchase invoice cancelled.');
    }

    // ── Delete (draft only) ─────────────────────────────────────────────────

    public function delete(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);
        $this->requirePermission('purchases', 'delete');
        $pi = $this->findPI((int)$input['id'], $businessId);

        if ($pi->status !== 'draft')
            $this->fail('Only draft purchase invoices can be deleted.');

        DB::statement("DELETE FROM purchase_invoice_items WHERE pi_id = ?", [$pi->id]);
        DB::statement("DELETE FROM purchase_invoices WHERE id = ?", [$pi->id]);

        return $this->success(null, 'Purchase invoice deleted.');
    }

    // ── Delete payment ──────────────────────────────────────────────────────

    public function deletePayment(array $input): array
    {
        $this->validate(['id' => 'required|integer', 'payment_id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->requirePermission('purchases', 'edit');
        $pi = $this->findPI((int)$input['id'], $businessId);

        DB::statement("DELETE FROM purchase_payments WHERE id = ? AND pi_id = ?", [(int)$input['payment_id'], $pi->id]);

        $paid = (float)DB::selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total_paid FROM purchase_payments WHERE pi_id = ?",
            [$pi->id]
        )->total_paid;

        $due    = round((float)$pi->total - $paid, 2);
        $status = $paid <= 0 ? 'recorded' : ($due <= 0 ? 'paid' : 'partial');

        DB::statement(
            "UPDATE purchase_invoices SET amount_paid = ?, amount_due = ?, status = ? WHERE id = ?",
            [$paid, max(0, $due), $status, $pi->id]
        );

        return $this->success(null, 'Payment deleted.');
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function findPI(int $id, int $businessId): object
    {
        $pi = PITable::find($id);
        if (!$pi || (int)$pi->business_id !== $businessId || $pi->deleted_at)
            $this->fail('Purchase invoice not found.', 404);
        return $pi;
    }

    private function resolveSupplyType(int $businessId, int $supplierId, int $locationId = 0): string
    {
        $sellerStateId = null;
        if ($locationId) {
            $loc = DB::selectOne("SELECT state_id FROM inventory_locations WHERE id = ? AND business_id = ? LIMIT 1", [$locationId, $businessId]);
            if ($loc && $loc->state_id) $sellerStateId = $loc->state_id;
        }
        if (!$sellerStateId) {
            $biz = DB::selectOne("SELECT state_id FROM businesses WHERE id = ? LIMIT 1", [$businessId]);
            $sellerStateId = $biz->state_id ?? null;
        }
        $supplier = DB::selectOne("SELECT state_id FROM clients WHERE id = ? LIMIT 1", [$supplierId]);
        if (!$sellerStateId || !$supplier) return 'intra';
        return ($sellerStateId == $supplier->state_id) ? 'intra' : 'inter';
    }

    private function validateItems(array $items): void
    {
        if (empty($items)) $this->fail('At least one item is required.');
        foreach ($items as $i => $item) {
            if (empty($item['description']))
                $this->fail("Item " . ($i + 1) . ": description is required.");
            if ((float)($item['quantity'] ?? 0) <= 0)
                $this->fail("Item " . ($i + 1) . ": quantity must be > 0.");
        }
    }

    private function calculateTotals(array $items, string $supplyType): array
    {
        $subtotal = $cgst = $sgst = $igst = $discount = 0.0;

        foreach ($items as $item) {
            $qty   = (float)($item['quantity']   ?? 1);
            $price = (float)($item['unit_price'] ?? 0);
            $rate  = (float)($item['gst_rate']   ?? 0);
            $discPct = (float)($item['discount_pct'] ?? 0);

            $lineGross = $qty * $price;
            $lineDisc  = round($lineGross * ($discPct / 100), 2);
            $taxable   = $lineGross - $lineDisc;
            $discount += $lineDisc;
            $subtotal += $taxable;

            if ($supplyType === 'inter') {
                $igst += round($taxable * ($rate / 100), 2);
            } else {
                $half = $rate / 2;
                $cgst += round($taxable * ($half / 100), 2);
                $sgst += round($taxable * ($half / 100), 2);
            }
        }

        $taxTotal = $cgst + $sgst + $igst;
        $rawTotal = $subtotal + $taxTotal;
        $rounded  = round($rawTotal);
        $roundOff = round($rounded - $rawTotal, 2);

        return [
            'subtotal'   => round($subtotal, 2),
            'cgst_total' => round($cgst, 2),
            'sgst_total' => round($sgst, 2),
            'igst_total' => round($igst, 2),
            'tax_total'  => round($taxTotal, 2),
            'discount'   => round($discount, 2),
            'round_off'  => $roundOff,
            'total'       => $rounded,
        ];
    }

    private function saveItems(int $piId, array $items, string $supplyType): void
    {
        foreach ($items as $i => $item) {
            $qty      = (float)($item['quantity']   ?? 1);
            $price    = (float)($item['unit_price'] ?? 0);
            $rate     = (float)($item['gst_rate']   ?? 0);
            $discPct  = (float)($item['discount_pct'] ?? 0);

            $lineGross = $qty * $price;
            $discAmt   = round($lineGross * ($discPct / 100), 2);
            $taxable   = $lineGross - $discAmt;

            $cgstRate = $sgstRate = $igstRate = 0;
            $cgstAmt  = $sgstAmt  = $igstAmt  = 0;

            if ($supplyType === 'inter') {
                $igstRate = $rate;
                $igstAmt  = round($taxable * ($rate / 100), 2);
            } else {
                $cgstRate = $sgstRate = $rate / 2;
                $cgstAmt  = round($taxable * ($cgstRate / 100), 2);
                $sgstAmt  = round($taxable * ($sgstRate / 100), 2);
            }

            PurchaseInvoiceItem::create([
                'pi_id'       => $piId,
                'product_id'  => !empty($item['product_id']) ? (int)$item['product_id'] : null,
                'description' => $item['description'],
                'hsn_sac'     => $item['hsn_sac'] ?? null,
                'unit'        => $item['unit']     ?? 'Nos',
                'quantity'    => $qty,
                'unit_price'  => $price,
                'discount_pct'=> $discPct,
                'discount_amt'=> $discAmt,
                'taxable_amt' => $taxable,
                'gst_rate'    => $rate,
                'cgst_rate'   => $cgstRate,
                'sgst_rate'   => $sgstRate,
                'igst_rate'   => $igstRate,
                'cgst_amt'    => $cgstAmt,
                'sgst_amt'    => $sgstAmt,
                'igst_amt'    => $igstAmt,
                'total'       => $taxable + $cgstAmt + $sgstAmt + $igstAmt,
                'sort_order'  => $i,
            ]);
        }
    }
}
