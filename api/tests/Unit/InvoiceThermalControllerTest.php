<?php

namespace Tests\Unit;

use App\Controllers\InvoiceThermalController;
use PHPUnit\Framework\TestCase;

class InvoiceThermalControllerTest extends TestCase
{
    public function test_receipt_contains_billing_and_tax_details_in_bluetooth_print_format(): void
    {
        $entries = InvoiceThermalController::formatReceipt(
            [
                'invoice_type' => 'tax_invoice', 'number' => 'INV/001', 'issue_date' => '2026-09-19',
                'client_name' => 'Customer', 'subtotal' => 200, 'discount' => 20,
                'cgst_total' => 16.2, 'sgst_total' => 16.2, 'total' => 212,
                'amount_paid' => 50, 'amount_due' => 162,
            ],
            ['name' => 'Test Shop', 'gstin' => '29ABCDE1234F1Z5'],
            [['description' => 'Paper rolls', 'quantity' => 2, 'unit' => 'Nos', 'unit_price' => 100, 'total' => 212.4, 'gst_rate' => 18, 'hsn_sac' => '4811']]
        );

        $text = implode("\n", array_column($entries, 'content'));
        foreach (['Test Shop', 'INV/001', 'Paper rolls', 'GST 18%', 'CGST: Rs 16.20', 'SGST: Rs 16.20', 'TOTAL: Rs 212.00', 'Balance due: Rs 162.00'] as $expected) {
            self::assertStringContainsString($expected, $text);
        }
        foreach ($entries as $entry) {
            self::assertSame(0, $entry['type']);
            self::assertContains($entry['align'], [0, 1, 2]);
        }
        $raw = InvoiceThermalController::escPos($entries);
        self::assertStringStartsWith("\x1B\x40", $raw);
        self::assertStringContainsString('TOTAL: Rs 212.00', $raw);
        self::assertStringContainsString("\x1B\x61\x01", $raw);
    }
}
