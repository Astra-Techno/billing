<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Tables\DeliveryChallan as DCTable;
use App\Tables\DeliveryChallanItem;

class DeliveryChallan extends Task
{
    protected bool $useTransaction = true;

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(array $input): array
    {
        $this->validate([
            'client_id'    => 'required|integer',
            'challan_date' => 'required|date',
            'items'        => 'required',
        ]);

        $businessId = $this->requireBusiness();
        $number     = Sequence::generate($businessId, 'dc');

        $dc = DCTable::create([
            'business_id' => $businessId,
            'created_by'  => $this->userId(),
            'client_id'   => (int)$input['client_id'],
            'number'      => $number,
            'status'      => 'draft',
            'challan_date' => $input['challan_date'],
            'vehicle_no'  => $input['vehicle_no']  ?? null,
            'driver_name' => $input['driver_name'] ?? null,
            'destination' => $input['destination'] ?? null,
            'notes'       => $input['notes']       ?? null,
        ]);

        $this->saveItems((int)$dc->id, $input['items'] ?? []);

        return $this->success([
            'id'     => $dc->id,
            'number' => $number,
        ], 'Delivery challan created.');
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(array $input): array
    {
        $this->validate([
            'id'        => 'required|integer',
            'client_id' => 'required|integer',
        ]);

        $businessId = $this->requireBusiness();
        $dc = DCTable::find((int)$input['id']);

        if (!$dc || $dc->business_id != $businessId)
            $this->fail('Delivery challan not found.');

        $dc->fill([
            'client_id'   => (int)$input['client_id'],
            'challan_date' => $input['challan_date'] ?? $dc->challan_date,
            'vehicle_no'  => $input['vehicle_no']   ?? $dc->vehicle_no,
            'driver_name' => $input['driver_name']  ?? $dc->driver_name,
            'destination' => $input['destination']  ?? $dc->destination,
            'notes'       => $input['notes']        ?? $dc->notes,
        ]);
        $dc->save();

        if (!empty($input['items'])) {
            DB::statement("DELETE FROM delivery_challan_items WHERE dc_id = ?", [$dc->id]);
            $this->saveItems((int)$dc->id, $input['items']);
        }

        return $this->success(['id' => $dc->id], 'Delivery challan updated.');
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function delete(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $dc = DCTable::find((int)$input['id']);

        if (!$dc || $dc->business_id != $businessId)
            $this->fail('Delivery challan not found.');

        DB::statement("DELETE FROM delivery_challan_items WHERE dc_id = ?", [$dc->id]);
        $dc->delete();

        return $this->success(null, 'Delivery challan deleted.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function saveItems(int $dcId, array $items): void
    {
        foreach ($items as $i => $it) {
            if (empty($it['description'])) continue;
            DeliveryChallanItem::create([
                'dc_id' => $dcId,
                'product_id'          => !empty($it['product_id']) ? (int)$it['product_id'] : null,
                'description'         => trim($it['description']),
                'hsn_sac'             => $it['hsn_sac'] ?? null,
                'unit'                => $it['unit']    ?? 'Nos',
                'quantity'            => (float)($it['quantity'] ?? 1),
                'sort_order'          => $i,
            ]);
        }
    }
}
