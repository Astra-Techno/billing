<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Tables\PurchaseOrder as POTable;
use App\Tables\PurchaseOrderItem;

class PurchaseOrder extends Task
{
    protected bool $useTransaction = true;

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(array $input): array
    {
        $this->validate([
            'supplier_id' => 'required|integer',
            'order_date'  => 'required|date',
            'items'       => 'required',
        ]);

        $businessId = $this->requireBusiness();
        $this->validateItems($input['items'] ?? []);

        $totals = $this->calculateTotals($input['items']);
        $number = Sequence::generate($businessId, 'po');

        $po = POTable::create([
            'business_id'   => $businessId,
            'created_by'    => $this->userId(),
            'supplier_id'   => (int)$input['supplier_id'],
            'number'        => $number,
            'status'        => 'draft',
            'order_date'    => $input['order_date'],
            'expected_date' => $input['expected_date'] ?? null,
            'subtotal'       => $totals['subtotal'],
            'tax_total'      => $totals['tax_total'],
            'total'          => $totals['total'],
            'notes'          => $input['notes'] ?? null,
        ]);

        $this->saveItems((int)$po->id, $input['items']);

        return $this->success([
            'id'     => $po->id,
            'number' => $number,
            'total'  => $totals['total'],
        ], 'Purchase order created.');
    }

    // ── Update (draft only) ───────────────────────────────────────────────────

    public function update(array $input): array
    {
        $this->validate([
            'id'          => 'required|integer',
            'supplier_id' => 'required|integer',
            'items'       => 'required',
        ]);

        $businessId = $this->requireBusiness();
        $po         = $this->findPO((int)$input['id'], $businessId);

        if ($po->status !== 'draft')
            $this->fail('Only draft purchase orders can be edited.');

        $this->validateItems($input['items'] ?? []);
        $totals = $this->calculateTotals($input['items']);

        $po->fill([
            'supplier_id'   => (int)$input['supplier_id'],
            'order_date'    => $input['order_date']    ?? $po->order_date,
            'expected_date' => $input['expected_date'] ?? $po->expected_date,
            'subtotal'      => $totals['subtotal'],
            'tax_total'     => $totals['tax_total'],
            'total'         => $totals['total'],
            'notes'         => $input['notes'] ?? $po->notes,
        ]);
        $po->save();

        DB::statement("DELETE FROM purchase_order_items WHERE po_id = ?", [$po->id]);
        $this->saveItems((int)$po->id, $input['items']);

        return $this->success(['id' => $po->id], 'Purchase order updated.');
    }

    // ── Send ──────────────────────────────────────────────────────────────────

    public function send(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $po         = $this->findPO((int)$input['id'], $businessId);

        if ($po->status !== 'draft')
            $this->fail('Only draft purchase orders can be sent.');

        DB::statement(
            "UPDATE purchase_orders SET status = 'sent' WHERE id = ?",
            [$po->id]
        );

        return $this->success(null, 'Purchase order marked as sent.');
    }

    // ── Receive ───────────────────────────────────────────────────────────────

    public function receive(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $po         = $this->findPO((int)$input['id'], $businessId);

        if (!in_array($po->status, ['draft', 'sent']))
            $this->fail('Purchase order cannot be marked as received.');

        DB::statement(
            "UPDATE purchase_orders SET status = 'received' WHERE id = ?",
            [$po->id]
        );

        return $this->success(null, 'Purchase order marked as received.');
    }

    // ── Cancel ────────────────────────────────────────────────────────────────

    public function cancel(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $po         = $this->findPO((int)$input['id'], $businessId);

        if ($po->status === 'received')
            $this->fail('Received purchase orders cannot be cancelled.');

        DB::statement(
            "UPDATE purchase_orders SET status = 'cancelled' WHERE id = ?",
            [$po->id]
        );

        return $this->success(null, 'Purchase order cancelled.');
    }

    // ── Delete (draft only) ───────────────────────────────────────────────────

    public function delete(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);
        $po         = $this->findPO((int)$input['id'], $businessId);

        if ($po->status !== 'draft')
            $this->fail('Only draft purchase orders can be deleted.');

        DB::statement("DELETE FROM purchase_order_items WHERE po_id = ?", [$po->id]);
        DB::statement("DELETE FROM purchase_orders WHERE id = ?", [$po->id]);

        return $this->success(null, 'Purchase order deleted.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function findPO(int $id, int $businessId): object
    {
        $po = POTable::find($id);
        if (!$po || (int)$po->business_id !== $businessId)
            $this->fail('Purchase order not found.', 404);
        return $po;
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

    private function calculateTotals(array $items): array
    {
        $subtotal = $taxTotal = 0.0;

        foreach ($items as $item) {
            $qty     = (float)($item['quantity']   ?? 1);
            $price   = (float)($item['unit_price'] ?? 0);
            $gstRate = (float)($item['gst_rate']   ?? 0);
            $line    = $qty * $price;
            $subtotal += $line;
            $taxTotal += round($line * ($gstRate / 100), 2);
        }

        $total = round($subtotal + $taxTotal);

        return compact('subtotal', 'taxTotal', 'total') + ['tax_total' => $taxTotal];
    }

    private function saveItems(int $poId, array $items): void
    {
        foreach ($items as $i => $item) {
            $qty     = (float)($item['quantity']   ?? 1);
            $price   = (float)($item['unit_price'] ?? 0);
            $gstRate = (float)($item['gst_rate']   ?? 0);
            $line    = $qty * $price;
            $taxAmt  = round($line * ($gstRate / 100), 2);

            PurchaseOrderItem::create([
                'po_id'       => $poId,
                'product_id'  => !empty($item['product_id']) ? (int)$item['product_id'] : null,
                'description' => $item['description'],
                'hsn_sac'     => $item['hsn_sac']   ?? null,
                'unit'        => $item['unit']       ?? 'Nos',
                'quantity'    => $qty,
                'unit_price'  => $price,
                'gst_rate'    => $gstRate,
                'total'       => $line + $taxAmt,
                'sort_order'  => $i,
            ]);
        }
    }
}
