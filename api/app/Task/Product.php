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

        return $this->success(['product_id' => $product->id], 'Product/service added.');
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
        $product    = $this->findProduct((int)$input['id'], $businessId);

        $product->setAttribute('active', 0);
        $product->save();

        return $this->success(null, 'Product deactivated.');
    }

    private function findProduct(int $id, int $businessId): object
    {
        $product = ProductTable::find($id);
        if (!$product || (int)$product->business_id !== $businessId)
            $this->fail('Product not found.', 404);
        return $product;
    }
}
