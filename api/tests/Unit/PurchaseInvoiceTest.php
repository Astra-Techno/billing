<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Purchase Invoice lifecycle tests.
 *
 * Tests the pure calculation and validation logic of PurchaseInvoice Task
 * without requiring a database connection. Uses reflection to call private methods.
 */
class PurchaseInvoiceTest extends TestCase
{
    private object $task;

    protected function setUp(): void
    {
        // Create a partial instance without constructor side effects
        $this->task = new class extends \App\Base\ClassObject {
            use PurchaseInvoiceMethods;
        };
    }

    // ── calculateTotals ──────────────────────────────────────────────────

    public function test_calculate_totals_single_item_intra_state(): void
    {
        $items = [
            ['quantity' => 10, 'unit_price' => 100, 'gst_rate' => 18, 'discount_pct' => 0],
        ];

        $totals = $this->callCalculateTotals($items, 'intra');

        $this->assertEquals(1000, $totals['subtotal']);
        $this->assertEquals(90, $totals['cgst_total']);     // 9% of 1000
        $this->assertEquals(90, $totals['sgst_total']);     // 9% of 1000
        $this->assertEquals(0, $totals['igst_total']);
        $this->assertEquals(180, $totals['tax_total']);
        $this->assertEquals(0, $totals['discount']);
        $this->assertEquals(1180, $totals['total']);
    }

    public function test_calculate_totals_single_item_inter_state(): void
    {
        $items = [
            ['quantity' => 10, 'unit_price' => 100, 'gst_rate' => 18, 'discount_pct' => 0],
        ];

        $totals = $this->callCalculateTotals($items, 'inter');

        $this->assertEquals(1000, $totals['subtotal']);
        $this->assertEquals(0, $totals['cgst_total']);
        $this->assertEquals(0, $totals['sgst_total']);
        $this->assertEquals(180, $totals['igst_total']);    // 18% of 1000
        $this->assertEquals(180, $totals['tax_total']);
        $this->assertEquals(1180, $totals['total']);
    }

    public function test_calculate_totals_with_discount(): void
    {
        $items = [
            ['quantity' => 5, 'unit_price' => 200, 'gst_rate' => 18, 'discount_pct' => 10],
        ];

        $totals = $this->callCalculateTotals($items, 'intra');

        // Gross = 1000, Discount = 100 (10%), Taxable = 900
        $this->assertEquals(900, $totals['subtotal']);
        $this->assertEquals(100, $totals['discount']);
        $this->assertEquals(81, $totals['cgst_total']);     // 9% of 900
        $this->assertEquals(81, $totals['sgst_total']);     // 9% of 900
        $this->assertEquals(162, $totals['tax_total']);
        $this->assertEquals(1062, $totals['total']);        // 900 + 162 = 1062
    }

    public function test_calculate_totals_round_off(): void
    {
        // Pick values that produce a non-integer total before rounding
        $items = [
            ['quantity' => 1, 'unit_price' => 99, 'gst_rate' => 5, 'discount_pct' => 0],
        ];

        $totals = $this->callCalculateTotals($items, 'intra');

        // Taxable = 99, CGST = 2.48, SGST = 2.48, raw total = 103.96
        $this->assertEquals(99, $totals['subtotal']);
        $this->assertEquals(104, $totals['total']);         // rounded
        $this->assertEquals(0.04, $totals['round_off']);    // 104 - 103.96
    }

    public function test_calculate_totals_zero_gst(): void
    {
        $items = [
            ['quantity' => 3, 'unit_price' => 50, 'gst_rate' => 0, 'discount_pct' => 0],
        ];

        $totals = $this->callCalculateTotals($items, 'intra');

        $this->assertEquals(150, $totals['subtotal']);
        $this->assertEquals(0, $totals['tax_total']);
        $this->assertEquals(150, $totals['total']);
    }

    public function test_calculate_totals_multiple_items(): void
    {
        $items = [
            ['quantity' => 2, 'unit_price' => 500, 'gst_rate' => 18, 'discount_pct' => 0],
            ['quantity' => 5, 'unit_price' => 100, 'gst_rate' => 12, 'discount_pct' => 5],
        ];

        $totals = $this->callCalculateTotals($items, 'intra');

        // Item 1: gross=1000, disc=0, taxable=1000, CGST=90, SGST=90
        // Item 2: gross=500, disc=25, taxable=475, CGST=28.50, SGST=28.50
        $this->assertEquals(1475, $totals['subtotal']);     // 1000 + 475
        $this->assertEquals(25, $totals['discount']);
        $this->assertEquals(118.50, $totals['cgst_total']); // 90 + 28.50
        $this->assertEquals(118.50, $totals['sgst_total']);
        $this->assertEquals(237, $totals['tax_total']);     // 118.50 * 2
        $this->assertEquals(1712, $totals['total']);        // 1475 + 237 = 1712
    }

    // ── validateItems ────────────────────────────────────────────────────

    public function test_validate_items_empty_fails(): void
    {
        $this->expectException(\Exception::class);
        $this->callValidateItems([]);
    }

    public function test_validate_items_missing_description_fails(): void
    {
        $this->expectException(\Exception::class);
        $this->callValidateItems([
            ['description' => '', 'quantity' => 1],
        ]);
    }

    public function test_validate_items_zero_quantity_fails(): void
    {
        $this->expectException(\Exception::class);
        $this->callValidateItems([
            ['description' => 'Widget', 'quantity' => 0],
        ]);
    }

    public function test_validate_items_valid_passes(): void
    {
        // Should not throw
        $this->callValidateItems([
            ['description' => 'Widget', 'quantity' => 5],
        ]);
        $this->assertTrue(true); // reached without exception
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function callCalculateTotals(array $items, string $supplyType): array
    {
        $task = new \App\Task\PurchaseInvoice();
        $method = new ReflectionMethod($task, 'calculateTotals');
        $method->setAccessible(true);
        return $method->invoke($task, $items, $supplyType);
    }

    private function callValidateItems(array $items): void
    {
        $task = new \App\Task\PurchaseInvoice();
        $method = new ReflectionMethod($task, 'validateItems');
        $method->setAccessible(true);
        $method->invoke($task, $items);
    }
}

// Trait extracted for test helper — not used outside tests
trait PurchaseInvoiceMethods {}
