<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Core\InventoryStock;
use App\Tables\PurchaseReturn as PRTable;
use App\Tables\PurchaseReturnItem;

class PurchaseReturn extends Task
{
    protected bool $useTransaction = true;

    // ── Create ──────────────────────────────────────────────────────────────

    public function create(array $input): array
    {
        $this->validate([
            'pi_id'       => 'required|integer',
            'reason'      => 'required|in:defective,expired,excess,wrong_item,other',
            'return_date' => 'required|date',
            'items'       => 'required',
        ]);

        $businessId = $this->requireBusiness();
        $this->requirePermission('purchases', 'create');
        $this->requireFeature($businessId, 'purchases');
        $items = $input['items'] ?? [];
        if (empty($items)) $this->fail('At least one item is required.');

        $pi = DB::selectOne(
            "SELECT * FROM purchase_invoices WHERE id = ? AND business_id = ? AND deleted_at IS NULL LIMIT 1",
            [(int)$input['pi_id'], $businessId]
        );
        if (!$pi) $this->fail('Purchase invoice not found.', 404);
        if ($pi->status === 'cancelled') $this->fail('Cannot return items from a cancelled purchase invoice.');
        if ($pi->status === 'draft') $this->fail('Purchase invoice must be recorded before returning items.');

        $supplyType = $pi->supply_type ?: 'intra';
        $totals     = $this->calculateTotals($items, $supplyType);
        $number     = Sequence::generate($businessId, 'pr');

        $pr = PRTable::create([
            'business_id'    => $businessId,
            'created_by'     => $this->userId(),
            'pi_id'          => (int)$input['pi_id'],
            'supplier_id'    => $pi->supplier_id,
            'location_id'    => $pi->location_id,
            'number'         => $number,
            'reason'         => $input['reason'],
            'return_date'    => $input['return_date'],
            'supply_type'    => $supplyType,
            'place_of_supply'=> $pi->place_of_supply,
            'subtotal'       => $totals['subtotal'],
            'cgst_total'     => $totals['cgst_total'],
            'sgst_total'     => $totals['sgst_total'],
            'igst_total'     => $totals['igst_total'],
            'tax_total'      => $totals['tax_total'],
            'total'          => $totals['total'],
            'status'         => 'draft',
            'notes'          => $input['notes'] ?? null,
        ]);

        $this->saveItems((int)$pr->id, $items, $supplyType);

        return $this->success([
            'id'     => $pr->id,
            'number' => $number,
            'total'  => $totals['total'],
        ], 'Purchase return created.');
    }

    // ── Issue (finalize and reverse stock) ───────────────────────────────

    public function issue(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->requirePermission('purchases', 'edit');
        $this->requireFeature($businessId, 'purchases');
        $pr = $this->findPR((int)$input['id'], $businessId);

        if ($pr->status !== 'draft') $this->fail('Only draft purchase returns can be issued.');

        DB::statement("UPDATE purchase_returns SET status = 'issued' WHERE id = ?", [$pr->id]);

        // Reverse stock (return items back to supplier = stock outward)
        $locId = (int)($pr->location_id ?: InventoryStock::defaultLocation($businessId));
        InventoryStock::postDocument($businessId, $locId, 'purchase_return', (int)$pr->id, 'purchase_return_items', 'pr_id', 'purchase_return', -1, $this->userId());

        return $this->success(['number' => $pr->number], 'Purchase return issued and stock reversed.');
    }

    // ── Adjust against purchase invoice balance ─────────────────────────

    public function adjust(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->requirePermission('purchases', 'edit');
        $this->requireFeature($businessId, 'purchases');
        $pr = $this->findPR((int)$input['id'], $businessId);

        if ($pr->status !== 'issued') $this->fail('Purchase return must be issued before adjustment.');

        $pi = DB::selectOne(
            "SELECT * FROM purchase_invoices WHERE id = ? AND deleted_at IS NULL LIMIT 1",
            [$pr->pi_id]
        );
        if (!$pi) $this->fail('Original purchase invoice not found.', 404);

        $returnAmount = (float)$pr->total;
        $newDue  = max(0, round((float)$pi->amount_due - $returnAmount, 2));
        $newPaid = round((float)$pi->total - $newDue, 2);
        $status  = $newDue <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : $pi->status);

        DB::statement(
            "UPDATE purchase_invoices SET amount_due = ?, amount_paid = ?, status = ? WHERE id = ?",
            [$newDue, $newPaid, $status, $pi->id]
        );
        DB::statement("UPDATE purchase_returns SET status = 'adjusted' WHERE id = ?", [$pr->id]);

        return $this->success(['invoice_balance' => $newDue], 'Purchase return adjusted against purchase invoice.');
    }

    // ── Delete (draft only) ─────────────────────────────────────────────

    public function delete(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);
        $this->requirePermission('purchases', 'delete');
        $this->requireFeature($businessId, 'purchases');
        $pr = $this->findPR((int)$input['id'], $businessId);

        if ($pr->status !== 'draft')
            $this->fail('Only draft purchase returns can be deleted.');

        DB::statement("DELETE FROM purchase_return_items WHERE pr_id = ?", [$pr->id]);
        DB::statement("DELETE FROM purchase_returns WHERE id = ?", [$pr->id]);

        return $this->success(null, 'Purchase return deleted.');
    }

    // ── Private helpers ─────────────────────────────────────────────────

    private function findPR(int $id, int $businessId): object
    {
        $pr = PRTable::find($id);
        if (!$pr || (int)$pr->business_id !== $businessId)
            $this->fail('Purchase return not found.', 404);
        return $pr;
    }

    private function calculateTotals(array $items, string $supplyType): array
    {
        $subtotal = $cgst = $sgst = $igst = 0.0;

        foreach ($items as $item) {
            $qty   = (float)($item['quantity']   ?? 1);
            $price = (float)($item['unit_price'] ?? 0);
            $rate  = (float)($item['gst_rate']   ?? 0);
            $taxable = $qty * $price;
            $subtotal += $taxable;

            if ($supplyType === 'intra') {
                $cgst += round($taxable * ($rate / 2 / 100), 2);
                $sgst += round($taxable * ($rate / 2 / 100), 2);
            } else {
                $igst += round($taxable * ($rate / 100), 2);
            }
        }

        $taxTotal = $cgst + $sgst + $igst;
        $total    = round($subtotal + $taxTotal);

        return [
            'subtotal'   => round($subtotal, 2),
            'cgst_total' => round($cgst, 2),
            'sgst_total' => round($sgst, 2),
            'igst_total' => round($igst, 2),
            'tax_total'  => round($taxTotal, 2),
            'total'      => $total,
        ];
    }

    private function saveItems(int $prId, array $items, string $supplyType): void
    {
        foreach ($items as $i => $item) {
            $qty   = (float)($item['quantity']   ?? 1);
            $price = (float)($item['unit_price'] ?? 0);
            $rate  = (float)($item['gst_rate']   ?? 0);
            $taxable = $qty * $price;

            $cgstAmt = $sgstAmt = $igstAmt = 0.0;
            if ($supplyType === 'intra') {
                $cgstAmt = round($taxable * ($rate / 2 / 100), 2);
                $sgstAmt = round($taxable * ($rate / 2 / 100), 2);
            } else {
                $igstAmt = round($taxable * ($rate / 100), 2);
            }

            PurchaseReturnItem::create([
                'pr_id'       => $prId,
                'product_id'  => !empty($item['product_id']) ? (int)$item['product_id'] : null,
                'description' => $item['description'],
                'hsn_sac'     => $item['hsn_sac'] ?? null,
                'unit'        => $item['unit']     ?? 'Nos',
                'quantity'    => $qty,
                'unit_price'  => $price,
                'taxable_amt' => $taxable,
                'gst_rate'    => $rate,
                'cgst_amt'    => $cgstAmt,
                'sgst_amt'    => $sgstAmt,
                'igst_amt'    => $igstAmt,
                'total'       => $taxable + $cgstAmt + $sgstAmt + $igstAmt,
                'batch_no'    => $item['batch_no'] ?? null,
                'expiry_date' => !empty($item['expiry_date']) ? $item['expiry_date'] : null,
                'sort_order'  => $i,
            ]);
        }
    }
}
