<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Tables\TaxRate as TaxRateTable;

class TaxRate extends Task
{
    public function create(array $input): array
    {
        $this->validate([
            'name' => 'required|string',
            'rate' => 'required|numeric',
        ]);

        $businessId = $this->requireBusiness();
        $rate       = (float)$input['rate'];

        $taxRate = TaxRateTable::create([
            'business_id' => $businessId,
            'name'        => trim($input['name']),
            'rate'        => $rate,
            'cgst_rate'   => $input['cgst_rate']  ?? round($rate / 2, 2),
            'sgst_rate'   => $input['sgst_rate']  ?? round($rate / 2, 2),
            'igst_rate'   => $input['igst_rate']  ?? $rate,
            'utgst_rate'  => $input['utgst_rate'] ?? round($rate / 2, 2),
            'is_default'  => 0,
            'active'      => 1,
        ]);

        return $this->success(['id' => $taxRate->id], 'Tax rate created.');
    }

    public function update(array $input): array
    {
        $this->validate([
            'id'   => 'required|integer',
            'name' => 'required|string',
            'rate' => 'required|numeric',
        ]);

        $businessId = $this->requireBusiness();
        $taxRate    = $this->findTaxRate((int)$input['id'], $businessId);
        $rate       = (float)$input['rate'];

        $taxRate->fill([
            'name'       => trim($input['name']),
            'rate'       => $rate,
            'cgst_rate'  => $input['cgst_rate']  ?? round($rate / 2, 2),
            'sgst_rate'  => $input['sgst_rate']  ?? round($rate / 2, 2),
            'igst_rate'  => $input['igst_rate']  ?? $rate,
            'utgst_rate' => $input['utgst_rate'] ?? round($rate / 2, 2),
        ]);
        $taxRate->save();

        return $this->success(null, 'Tax rate updated.');
    }

    public function setDefault(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->findTaxRate((int)$input['id'], $businessId);

        DB::statement(
            "UPDATE tax_rates SET is_default = 0 WHERE business_id = ?",
            [$businessId]
        );
        DB::statement(
            "UPDATE tax_rates SET is_default = 1 WHERE id = ? AND business_id = ?",
            [(int)$input['id'], $businessId]
        );

        return $this->success(null, 'Default tax rate updated.');
    }

    public function delete(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);
        $taxRate    = $this->findTaxRate((int)$input['id'], $businessId);

        // Check if in use
        $inUse = DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM products WHERE tax_rate_id = ?",
            [$taxRate->id]
        );
        if ((int)($inUse->cnt ?? 0) > 0) {
            $this->fail('Cannot delete — this tax rate is used by products.');
        }

        DB::statement("UPDATE tax_rates SET active = 0 WHERE id = ?", [$taxRate->id]);

        return $this->success(null, 'Tax rate removed.');
    }

    private function findTaxRate(int $id, int $businessId): object
    {
        $tr = TaxRateTable::find($id);
        if (!$tr || (int)$tr->business_id !== $businessId)
            $this->fail('Tax rate not found.', 404);
        return $tr;
    }
}
