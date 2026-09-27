<?php

namespace App\Core;

use RuntimeException;

final class InventoryStock
{
    public static function settings(int $businessId): object
    {
        $business = DB::selectOne('SELECT inventory_mode, inventory_advanced FROM businesses WHERE id = ?', [$businessId]);
        if (!$business) throw new RuntimeException('Business not found.');
        return $business;
    }

    public static function defaultLocation(int $businessId): int
    {
        $row = DB::selectOne('SELECT id FROM inventory_locations WHERE business_id = ? AND active = 1 ORDER BY is_default DESC, id ASC LIMIT 1', [$businessId]);
        if ($row) return (int)$row->id;
        DB::statement("INSERT INTO inventory_locations (business_id, name, type, is_default) VALUES (?, 'Main Shop', 'shop', 1)", [$businessId]);
        return (int)DB::lastInsertId();
    }

    public static function move(int $businessId, int $locationId, int $productId, float $quantity, string $type, ?string $referenceType, ?int $referenceId, int $userId, array $meta = []): int
    {
        if (abs($quantity) < 0.0005) throw new RuntimeException('Stock quantity must not be zero.');
        $product = DB::selectOne('SELECT id, name, track_stock, purchase_price FROM products WHERE id = ? AND business_id = ? AND active = 1', [$productId, $businessId]);
        if (!$product || !(int)$product->track_stock) return 0;
        $location = DB::selectOne('SELECT id FROM inventory_locations WHERE id = ? AND business_id = ? AND active = 1', [$locationId, $businessId]);
        if (!$location) throw new RuntimeException('Shop / Godown not found.');

        DB::statement('INSERT IGNORE INTO stock_balances (business_id, location_id, product_id) VALUES (?, ?, ?)', [$businessId, $locationId, $productId]);
        $balance = DB::selectOne('SELECT quantity, average_cost FROM stock_balances WHERE business_id = ? AND location_id = ? AND product_id = ? FOR UPDATE', [$businessId, $locationId, $productId]);
        $before = (float)$balance->quantity;
        $after = round($before + $quantity, 3);
        $settings = self::settings($businessId);
        if ($after < 0 && $settings->inventory_mode === 'strict') {
            throw new RuntimeException("Only " . max(0, $before) . " available for {$product->name} in this shop.");
        }
        $cost = isset($meta['unit_cost']) ? (float)$meta['unit_cost'] : (float)$product->purchase_price;
        $average = (float)$balance->average_cost;
        if ($quantity > 0 && $cost > 0) {
            $positiveBefore = max(0, $before);
            $average = round((($positiveBefore * $average) + ($quantity * $cost)) / max(0.001, $positiveBefore + $quantity), 4);
        }
        DB::statement('UPDATE stock_balances SET quantity = ?, average_cost = ? WHERE business_id = ? AND location_id = ? AND product_id = ?', [$after, $average, $businessId, $locationId, $productId]);
        DB::statement('INSERT INTO stock_movements (business_id, location_id, product_id, movement_type, quantity, unit_cost, balance_after, reference_type, reference_id, reversal_of_id, batch_no, expiry_date, serial_numbers, note, occurred_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $businessId, $locationId, $productId, $type, $quantity, $cost, $after, $referenceType, $referenceId,
            $meta['reversal_of_id'] ?? null, $meta['batch_no'] ?? null, !empty($meta['expiry_date']) ? $meta['expiry_date'] : null,
            isset($meta['serial_numbers']) ? json_encode((array)$meta['serial_numbers']) : null,
            $meta['note'] ?? null, $meta['occurred_at'] ?? date('Y-m-d H:i:s'), $userId,
        ]);
        return (int)DB::lastInsertId();
    }

    public static function postDocument(int $businessId, int $locationId, string $referenceType, int $referenceId, string $itemsTable, string $foreignKey, string $movementType, float $sign, int $userId): void
    {
        if (self::settings($businessId)->inventory_mode === 'none') return;
        $exists = DB::selectOne('SELECT id FROM stock_movements WHERE business_id = ? AND reference_type = ? AND reference_id = ? LIMIT 1', [$businessId, $referenceType, $referenceId]);
        if ($exists) return;
        $items = DB::select("SELECT product_id, quantity, unit_price FROM {$itemsTable} WHERE {$foreignKey} = ? AND product_id IS NOT NULL", [$referenceId]);
        foreach ($items as $item) {
            $factor = 1.0;
            if ($movementType === 'purchase') {
                $product = DB::selectOne('SELECT conversion_factor FROM products WHERE id=? AND business_id=?', [(int)$item->product_id, $businessId]);
                $factor = max(0.0001, (float)($product->conversion_factor ?? 1));
            }
            self::move($businessId, $locationId, (int)$item->product_id, $sign * (float)$item->quantity * $factor, $movementType, $referenceType, $referenceId, $userId, ['unit_cost' => (float)$item->unit_price / $factor]);
        }
    }

    public static function reverseDocument(int $businessId, string $referenceType, int $referenceId, int $userId): void
    {
        $rows = DB::select('SELECT * FROM stock_movements WHERE business_id = ? AND reference_type = ? AND reference_id = ? AND reversal_of_id IS NULL ORDER BY id', [$businessId, $referenceType, $referenceId]);
        foreach ($rows as $row) {
            $reversed = DB::selectOne('SELECT id FROM stock_movements WHERE reversal_of_id = ?', [$row->id]);
            if ($reversed) continue;
            self::move($businessId, (int)$row->location_id, (int)$row->product_id, -(float)$row->quantity, $row->quantity < 0 ? 'sale_return' : 'purchase_return', $referenceType . '_reversal', $referenceId, $userId, ['unit_cost' => $row->unit_cost, 'reversal_of_id' => $row->id, 'note' => 'Automatic reversal']);
        }
    }
}
