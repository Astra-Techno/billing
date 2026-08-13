<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Tables\Product as ProductTable;

class Product extends Task
{
    public function create(array $input): array
    {
        $this->validate([
            'type'  => 'required|in:product,service',
            'name'  => 'required|string|min_length:2',
            'price' => 'required|numeric',
        ]);

        $businessId = $this->requireBusiness();

        $exists = DB::selectOne(
            "SELECT id FROM products WHERE business_id = ? AND LOWER(name) = LOWER(?) AND active = 1 LIMIT 1",
            [$businessId, trim($input['name'])]
        );
        if ($exists)
            $this->fail('A product/service with this name already exists.');

        $product = ProductTable::create([
            'business_id' => $businessId,
            'type'        => $input['type'],
            'name'        => trim($input['name']),
            'description' => $input['description'] ?? null,
            'hsn_sac'     => $input['hsn_sac']     ?? null,
            'unit'        => $input['unit']         ?? 'Nos',
            'price'       => (float)$input['price'],
            'tax_rate_id' => !empty($input['tax_rate_id']) ? (int)$input['tax_rate_id'] : null,
            'sku'         => $input['sku']          ?? null,
            'active'      => 1,
        ]);

        return $this->success([
            'product_id'  => $product->id,
            'id'          => $product->id,
            'name'        => $product->name,
            'price'       => $product->price,
            'unit'        => $product->unit,
            'hsn_sac'     => $product->hsn_sac,
            'description' => $product->description,
            'type'        => $product->type,
        ], 'Product/service added.');
    }

    public function update(array $input): array
    {
        $this->validate([
            'id'    => 'required|integer',
            'name'  => 'required|string',
            'price' => 'required|numeric',
        ]);

        $businessId = $this->requireBusiness();
        $product    = $this->findProduct((int)$input['id'], $businessId);

        $product->fill([
            'type'        => $input['type']        ?? $product->type,
            'name'        => trim($input['name']),
            'description' => $input['description'] ?? $product->description,
            'hsn_sac'     => $input['hsn_sac']     ?? $product->hsn_sac,
            'unit'        => $input['unit']         ?? $product->unit,
            'price'       => (float)$input['price'],
            'tax_rate_id' => !empty($input['tax_rate_id']) ? (int)$input['tax_rate_id'] : $product->tax_rate_id,
            'sku'         => $input['sku']          ?? $product->sku,
        ]);
        $product->save();

        return $this->success(null, 'Product updated.');
    }

    public function delete(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);
        $product    = $this->findProduct((int)$input['id'], $businessId);

        $product->setAttribute('active', 0);
        $product->save();

        return $this->success(null, 'Product deactivated.');
    }

    public function import(array $input): array
    {
        $this->validate(['csv' => 'required|string']);

        $businessId = $this->requireBusiness();

        $csv = trim($input['csv']);
        $lines = preg_split('/\r?\n/', $csv);
        if (count($lines) < 2)
            $this->fail('CSV must have a header row and at least one data row.');

        // Parse header
        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        // Map column names
        $colMap = [
            'type'        => ['type'],
            'name'        => ['name', 'product name', 'item name', 'item'],
            'hsn_sac'     => ['hsn/sac', 'hsn_sac', 'hsn', 'sac'],
            'sku'         => ['sku'],
            'unit'        => ['unit', 'uom'],
            'price'       => ['price', 'rate', 'amount'],
            'tax_rate'    => ['tax rate %', 'tax rate', 'gst %', 'gst', 'tax %', 'tax'],
            'description' => ['description', 'desc'],
        ];

        $colIndex = [];
        foreach ($colMap as $field => $aliases) {
            foreach ($aliases as $alias) {
                $idx = array_search($alias, $header);
                if ($idx !== false) { $colIndex[$field] = $idx; break; }
            }
        }

        if (!isset($colIndex['name']))
            $this->fail('CSV must have a "Name" column.');

        // Load tax rates for matching
        $taxRates = DB::select(
            "SELECT id, rate FROM tax_rates WHERE business_id = ? AND active = 1",
            [$businessId]
        );
        $taxRateMap = [];
        foreach ($taxRates as $tr) {
            $taxRateMap[(string)$tr->rate] = $tr->id;
        }

        $created = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if ($line === '') continue;

            $cols = str_getcsv($line);
            $row  = $lineNum + 2; // 1-based, header was row 1

            $name = isset($colIndex['name']) ? trim($cols[$colIndex['name']] ?? '') : '';
            if ($name === '') {
                $errors[] = ['row' => $row, 'message' => 'Name is empty'];
                $skipped++;
                continue;
            }

            $type = isset($colIndex['type']) ? strtolower(trim($cols[$colIndex['type']] ?? '')) : 'product';
            if (!in_array($type, ['product', 'service'])) $type = 'product';

            $price = isset($colIndex['price']) ? (float)($cols[$colIndex['price']] ?? 0) : 0;

            // Check duplicate
            $exists = DB::selectOne(
                "SELECT id FROM products WHERE business_id = ? AND LOWER(name) = LOWER(?) AND active = 1 LIMIT 1",
                [$businessId, $name]
            );
            if ($exists) {
                $errors[] = ['row' => $row, 'message' => "\"$name\" already exists"];
                $skipped++;
                continue;
            }

            // Match tax rate
            $taxRateId = null;
            if (isset($colIndex['tax_rate'])) {
                $rate = trim(str_replace('%', '', $cols[$colIndex['tax_rate']] ?? ''));
                if ($rate !== '' && isset($taxRateMap[$rate])) {
                    $taxRateId = $taxRateMap[$rate];
                }
            }

            try {
                ProductTable::create([
                    'business_id' => $businessId,
                    'type'        => $type,
                    'name'        => $name,
                    'description' => isset($colIndex['description']) ? trim($cols[$colIndex['description']] ?? '') ?: null : null,
                    'hsn_sac'     => isset($colIndex['hsn_sac']) ? trim($cols[$colIndex['hsn_sac']] ?? '') ?: null : null,
                    'unit'        => isset($colIndex['unit']) ? trim($cols[$colIndex['unit']] ?? '') ?: 'Nos' : 'Nos',
                    'price'       => $price,
                    'tax_rate_id' => $taxRateId,
                    'sku'         => isset($colIndex['sku']) ? trim($cols[$colIndex['sku']] ?? '') ?: null : null,
                    'active'      => 1,
                ]);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $row, 'message' => "Failed: {$e->getMessage()}"];
                $skipped++;
            }
        }

        return $this->success([
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => $errors,
        ], "$created product(s) imported.");
    }

    private function findProduct(int $id, int $businessId): object
    {
        $product = ProductTable::find($id);
        if (!$product || (int)$product->business_id !== $businessId)
            $this->fail('Product not found.', 404);
        return $product;
    }
}
