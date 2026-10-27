<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductImportTest extends TestCase
{
    private array $colMap;
    private \Closure $parseBool;

    protected function setUp(): void
    {
        $this->colMap = [
            'type'               => ['type', 'item type'],
            'name'               => ['name', 'product name', 'item name', 'item'],
            'description'        => ['description', 'desc', 'note', 'notes'],
            'pos_category'       => ['pos category', 'pos_category', 'category'],
            'hsn_sac'            => ['hsn/sac', 'hsn_sac', 'hsn', 'sac'],
            'unit'               => ['unit', 'uom', 'billing unit'],
            'price'              => ['price', 'rate', 'amount', 'selling price'],
            'price_includes_tax' => ['includes gst', 'price includes tax', 'price_includes_tax', 'tax inclusive', 'gst inclusive', 'includes tax', 'tax included', 'is_tax_inclusive'],
            'discount_type'      => ['discount type', 'discount_type'],
            'discount_value'     => ['discount', 'discount value', 'discount_value', 'disc', 'disc %'],
            'purchase_price'     => ['purchase price', 'purchase_price', 'cost', 'cost price', 'buy price'],
            'mrp'                => ['mrp', 'max price', 'maximum retail price'],
            'tax_rate'           => ['tax rate %', 'tax rate', 'gst %', 'gst', 'tax %', 'tax'],
            'sku'                => ['sku', 'item code', 'product code'],
            'barcode'            => ['barcode', 'ean', 'upc'],
            'track_stock'        => ['track stock', 'track_stock', 'maintain stock', 'track stock?', 'inventory tracking', 'manage stock'],
            'reorder_level'      => ['reorder level', 'reorder_level', 'low stock alert', 'alert level', 'min stock', 'alert qty', 'reorder qty'],
            'base_unit'          => ['base unit', 'base_unit', 'purchase unit', 'purchase_unit'],
            'conversion_factor'  => ['conversion factor', 'conversion_factor', 'unit conversion', 'multiplier', 'conversion'],
            'batch_tracking'     => ['batch tracking', 'batch_tracking', 'track batch', 'batch', 'batch number', 'batch tracking?'],
            'expiry_tracking'    => ['expiry tracking', 'expiry_tracking', 'track expiry', 'expiry', 'expiry date', 'expiry tracking?'],
            'serial_tracking'    => ['serial tracking', 'serial_tracking', 'track serial', 'serial', 'imei', 'serial number', 'serial tracking?'],
            'active'             => ['status', 'active', 'is_active', 'enabled', 'item status'],
            'stock'              => ['stock', 'opening stock', 'qty', 'quantity'],
            'shop_price'         => ['shop price', 'shop_price', 'location price', 'branch price'],
            'shop_tax_rate'      => ['shop gst %', 'shop gst', 'shop tax rate %', 'shop tax rate', 'shop tax', 'location gst', 'location tax'],
        ];

        $this->parseBool = function($val, ?bool $default = null): ?bool {
            if ($val === null) return $default;
            $v = strtolower(trim((string)$val));
            if ($v === '') return $default;
            if (in_array($v, ['1', 'true', 'yes', 'y', 'active', 'on', 'checked'], true)) return true;
            if (in_array($v, ['0', 'false', 'no', 'n', 'inactive', 'off', 'unchecked'], true)) return false;
            return $default;
        };
    }

    public function test_all_product_edit_screen_fields_have_column_mappings(): void
    {
        $expectedFields = [
            'type', 'name', 'description', 'pos_category', 'hsn_sac',
            'unit', 'price', 'price_includes_tax', 'discount_type', 'discount_value',
            'purchase_price', 'mrp', 'tax_rate', 'sku', 'barcode',
            'track_stock', 'reorder_level', 'base_unit', 'conversion_factor',
            'batch_tracking', 'expiry_tracking', 'serial_tracking', 'active',
            'stock', 'shop_price', 'shop_tax_rate',
        ];

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $this->colMap, "Missing column mapping for: $field");
            $this->assertNotEmpty($this->colMap[$field]);
        }
    }

    public function test_header_resolution_matches_aliases(): void
    {
        $header = [
            'type', 'product name', 'category', 'hsn/sac', 'item code', 'barcode',
            'unit', 'selling price', 'includes gst', 'discount type', 'discount',
            'cost price', 'mrp', 'gst %', 'track stock', 'low stock alert',
            'purchase unit', 'unit conversion', 'batch tracking', 'expiry tracking',
            'serial tracking', 'status', 'description', 'stock', 'shop price', 'shop gst %'
        ];

        $colIndex = [];
        foreach ($this->colMap as $field => $aliases) {
            foreach ($aliases as $alias) {
                $idx = array_search(strtolower(trim($alias)), $header, true);
                if ($idx !== false) {
                    $colIndex[$field] = $idx;
                    break;
                }
            }
        }

        $this->assertCount(26, $colIndex, "All 26 fields should be resolved from header aliases.");
        $this->assertSame(0, $colIndex['type']);
        $this->assertSame(1, $colIndex['name']);
        $this->assertSame(8, $colIndex['price_includes_tax']);
        $this->assertSame(14, $colIndex['track_stock']);
        $this->assertSame(15, $colIndex['reorder_level']);
        $this->assertSame(16, $colIndex['base_unit']);
        $this->assertSame(17, $colIndex['conversion_factor']);
        $this->assertSame(18, $colIndex['batch_tracking']);
        $this->assertSame(19, $colIndex['expiry_tracking']);
        $this->assertSame(20, $colIndex['serial_tracking']);
        $this->assertSame(21, $colIndex['active']);
        $this->assertSame(25, $colIndex['shop_tax_rate']);
    }

    public function test_boolean_parser_accepts_common_truthy_and_falsy_values(): void
    {
        $parse = $this->parseBool;

        $this->assertTrue($parse('yes'));
        $this->assertTrue($parse('YES'));
        $this->assertTrue($parse('true'));
        $this->assertTrue($parse('1'));
        $this->assertTrue($parse('y'));
        $this->assertTrue($parse('active'));
        $this->assertTrue($parse('on'));

        $this->assertFalse($parse('no'));
        $this->assertFalse($parse('NO'));
        $this->assertFalse($parse('false'));
        $this->assertFalse($parse('0'));
        $this->assertFalse($parse('n'));
        $this->assertFalse($parse('inactive'));
        $this->assertFalse($parse('off'));

        $this->assertNull($parse(''));
        $this->assertNull($parse(null));
        $this->assertTrue($parse('', true));
        $this->assertFalse($parse(null, false));
    }
}
