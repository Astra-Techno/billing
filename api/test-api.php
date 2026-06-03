<?php
/**
 * API Test Script for CloudBill
 *
 * Usage: php test-api.php [base_url] [email] [password]
 * Example: php test-api.php https://billing.cloudkart24.com test@example.com password123
 *
 * Or edit the defaults below:
 */

$BASE_URL  = $argv[1] ?? 'https://billing.cloudkart24.com';
$EMAIL     = $argv[2] ?? '';
$PASSWORD  = $argv[3] ?? '';

if (!$EMAIL || !$PASSWORD) {
    echo "Usage: php test-api.php [base_url] [email] [password]\n";
    exit(1);
}

// ── Helpers ────────────────────────────────────────────────────────────────

$token = null;
$businessId = null;
$passed = 0;
$failed = 0;
$skipped = 0;
$errors = [];

function api(string $method, string $endpoint, array $data = [], bool $auth = true): array {
    global $BASE_URL, $token;
    $url = rtrim($BASE_URL, '/') . '/api/' . ltrim($endpoint, '/');

    $ch = curl_init();
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($auth && $token) $headers[] = "Authorization: Bearer {$token}";

    if ($method === 'GET' && $data) {
        $url .= '?' . http_build_query($data);
    }

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) return ['success' => false, 'message' => "CURL: $curlError", '_http' => 0];

    $json = json_decode($response, true) ?? ['success' => false, 'message' => "Invalid JSON: " . substr($response, 0, 200)];
    $json['_http'] = $httpCode;
    return $json;
}

function test(string $name, callable $fn): void {
    global $passed, $failed, $skipped, $errors;
    echo "  {$name} ... ";
    try {
        $result = $fn();
        if ($result === 'SKIP') {
            echo "\033[33mSKIPPED\033[0m\n";
            $skipped++;
        } else {
            echo "\033[32mPASS\033[0m\n";
            $passed++;
        }
    } catch (\Throwable $e) {
        echo "\033[31mFAIL\033[0m — {$e->getMessage()}\n";
        $failed++;
        $errors[] = "$name: {$e->getMessage()}";
    }
}

function assert_true(bool $condition, string $msg = 'Assertion failed'): void {
    if (!$condition) throw new \RuntimeException($msg);
}

function section(string $title): void {
    echo "\n\033[1;36m── {$title} ──\033[0m\n";
}

// ── Tests ──────────────────────────────────────────────────────────────────

section('AUTH');

test('Login', function() {
    global $token, $businessId, $EMAIL, $PASSWORD;
    $res = api('POST', 'task/Auth/login', ['email' => $EMAIL, 'password' => $PASSWORD], false);
    assert_true($res['success'] === true, $res['message'] ?? 'Login failed');
    $token = $res['data']['token'] ?? null;
    $businessId = $res['data']['businesses'][0]['id'] ?? null;
    assert_true(!empty($token), 'No token returned');
    assert_true(!empty($businessId), 'No business found');
});

// ── BUSINESS ───────────────────────────────────────────────────────────────

section('BUSINESS');

test('Fetch business', function() {
    $res = api('GET', 'item/Business');
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
    assert_true(!empty($res['data']['id']), 'No business data');
});

test('List Indian states', function() {
    $res = api('GET', 'list/IndianState');
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
    assert_true(is_array($res['data']) && count($res['data']) > 0, 'No states returned');
});

// ── PRODUCTS ───────────────────────────────────────────────────────────────

section('PRODUCTS');

$testProductId = null;
$testProductName = 'ZZ-Test-Product-' . time();

test('List products', function() {
    $res = api('GET', 'list/Product');
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
    assert_true(is_array($res['data']), 'Invalid data format');
});

test('Create product', function() {
    global $testProductId, $testProductName;
    $res = api('POST', 'task/Product/create', [
        'type'  => 'product',
        'name'  => $testProductName,
        'price' => 100,
        'unit'  => 'Nos',
    ]);
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
    $testProductId = $res['data']['product_id'] ?? $res['data']['id'] ?? null;
    assert_true(!empty($testProductId), 'No product_id returned');
    assert_true(!empty($res['data']['name']), 'Product name not returned');
    assert_true(!empty($res['data']['unit']), 'Product unit not returned');
});

test('Duplicate product blocked', function() {
    global $testProductName;
    $res = api('POST', 'task/Product/create', [
        'type'  => 'product',
        'name'  => $testProductName,
        'price' => 200,
        'unit'  => 'Nos',
    ]);
    assert_true($res['success'] === false, 'Duplicate was NOT blocked');
});

test('Update product', function() {
    global $testProductId, $testProductName;
    if (!$testProductId) return 'SKIP';
    $res = api('POST', 'task/Product/update', [
        'id'    => $testProductId,
        'name'  => $testProductName . '-Updated',
        'price' => 150,
        'unit'  => 'Box',
    ]);
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Delete product', function() {
    global $testProductId;
    if (!$testProductId) return 'SKIP';
    $res = api('POST', 'task/Product/delete', ['id' => $testProductId]);
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
});

// ── CLIENTS ────────────────────────────────────────────────────────────────

section('CLIENTS');

$testClientId = null;

test('List clients', function() {
    $res = api('GET', 'list/Client');
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
    assert_true(is_array($res['data']), 'Invalid data format');
});

test('Create client', function() {
    global $testClientId;
    $res = api('POST', 'task/Client/create', [
        'name'  => 'ZZ-Test-Client-' . time(),
        'email' => 'testclient' . time() . '@test.com',
        'phone' => '9876543210',
    ]);
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
    $testClientId = $res['data']['client_id'] ?? $res['data']['id'] ?? null;
    assert_true(!empty($testClientId), 'No client_id returned');
});

test('Fetch single client', function() {
    global $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('GET', 'item/Client', ['id' => $testClientId]);
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
});

// ── TAX RATES ──────────────────────────────────────────────────────────────

section('TAX RATES');

test('List tax rates', function() {
    $res = api('GET', 'list/TaxRate');
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
    assert_true(is_array($res['data']), 'Invalid data format');
});

// ── INVOICES ───────────────────────────────────────────────────────────────

section('INVOICES');

$testInvoiceId = null;

test('List invoices', function() {
    $res = api('GET', 'list/Invoice');
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
    assert_true(is_array($res['data']), 'Invalid data format');
});

test('Create invoice', function() {
    global $testInvoiceId, $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('POST', 'task/Invoice/create', [
        'client_id'    => $testClientId,
        'invoice_type' => 'tax_invoice',
        'issue_date'   => date('Y-m-d'),
        'due_date'     => date('Y-m-d', strtotime('+30 days')),
        'notes'        => 'Test invoice',
        'items'        => [
            [
                'description' => 'Test Item 1',
                'quantity'    => 2,
                'unit'        => 'Nos',
                'price'       => 500,
                'gst_rate'    => 18,
            ],
        ],
    ]);
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
    $testInvoiceId = $res['data']['invoice_id'] ?? $res['data']['id'] ?? null;
    assert_true(!empty($testInvoiceId), 'No invoice_id returned');
});

test('Fetch invoice', function() {
    global $testInvoiceId;
    if (!$testInvoiceId) return 'SKIP';
    $res = api('GET', 'item/Invoice', ['id' => $testInvoiceId]);
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Fetch invoice items', function() {
    global $testInvoiceId;
    if (!$testInvoiceId) return 'SKIP';
    $res = api('GET', 'list/Invoice:items', ['invoice_id' => $testInvoiceId]);
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
    assert_true(is_array($res['data']) && count($res['data']) > 0, 'No items returned');
});

test('Update invoice', function() {
    global $testInvoiceId, $testClientId;
    if (!$testInvoiceId) return 'SKIP';
    $res = api('POST', 'task/Invoice/update', [
        'id'           => $testInvoiceId,
        'client_id'    => $testClientId,
        'invoice_type' => 'tax_invoice',
        'issue_date'   => date('Y-m-d'),
        'due_date'     => date('Y-m-d', strtotime('+30 days')),
        'notes'        => 'Updated test invoice',
        'items'        => [
            [
                'description' => 'Updated Item 1',
                'quantity'    => 3,
                'unit'        => 'Nos',
                'price'       => 600,
                'gst_rate'    => 18,
            ],
        ],
    ]);
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
});

// ── QUOTES ─────────────────────────────────────────────────────────────────

section('QUOTES');

$testQuoteId = null;

test('List quotes', function() {
    $res = api('GET', 'list/Quote');
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Create quote', function() {
    global $testQuoteId, $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('POST', 'task/Quote/create', [
        'client_id'  => $testClientId,
        'issue_date' => date('Y-m-d'),
        'due_date'   => date('Y-m-d', strtotime('+15 days')),
        'notes'      => 'Test quote',
        'items'      => [
            [
                'description' => 'Quote Item 1',
                'quantity'    => 1,
                'unit'        => 'Nos',
                'price'       => 1000,
                'gst_rate'    => 18,
            ],
        ],
    ]);
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
    $testQuoteId = $res['data']['quote_id'] ?? $res['data']['id'] ?? null;
});

test('Convert quote to invoice', function() {
    global $testQuoteId;
    if (!$testQuoteId) return 'SKIP';
    $res = api('POST', 'task/Quote/convertToInvoice', ['id' => $testQuoteId]);
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
});

// ── EXPENSES ───────────────────────────────────────────────────────────────

section('EXPENSES');

test('List expenses', function() {
    $res = api('GET', 'list/Expense');
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
});

test('List expense categories', function() {
    $res = api('GET', 'list/ExpenseCategory');
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
});

// ── DASHBOARD & REPORTS ────────────────────────────────────────────────────

section('DASHBOARD & REPORTS');

test('Dashboard data', function() {
    $res = api('GET', 'list/Dashboard');
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Reports - revenue', function() {
    $res = api('GET', 'list/Report', [
        'type'      => 'revenue',
        'from_date' => date('Y-01-01'),
        'to_date'   => date('Y-m-d'),
    ]);
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
});

// ── SETTINGS ───────────────────────────────────────────────────────────────

section('SETTINGS');

test('Fetch settings', function() {
    $res = api('POST', 'task/Settings/get', []);
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
});

// ── DELIVERY CHALLANS ──────────────────────────────────────────────────────

section('DELIVERY CHALLANS');

test('List delivery challans', function() {
    $res = api('GET', 'list/DeliveryChallan');
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
});

// ── PURCHASE ORDERS ────────────────────────────────────────────────────────

section('PURCHASE ORDERS');

test('List purchase orders', function() {
    $res = api('GET', 'list/PurchaseOrder');
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
});

// ── CREDIT NOTES ───────────────────────────────────────────────────────────

section('CREDIT NOTES');

test('List credit notes', function() {
    $res = api('GET', 'list/Invoice', ['type' => 'credit_note']);
    assert_true($res['success'] === true, $res['message'] ?? 'Failed');
});

// ── CLEANUP ────────────────────────────────────────────────────────────────

section('CLEANUP');

test('Delete test invoice', function() {
    global $testInvoiceId;
    if (!$testInvoiceId) return 'SKIP';
    $res = api('POST', 'task/Invoice/delete', ['id' => $testInvoiceId]);
    // Some invoices can't be deleted if they have a sequence, that's ok
    assert_true($res['success'] === true || strpos($res['message'] ?? '', 'cannot') !== false, $res['message'] ?? 'Failed');
});

test('Delete test client', function() {
    global $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('POST', 'task/Client/delete', ['id' => $testClientId]);
    assert_true($res['success'] === true || strpos($res['message'] ?? '', 'open') !== false, $res['message'] ?? 'Failed');
});

// ── SUMMARY ────────────────────────────────────────────────────────────────

echo "\n\033[1m══════════════════════════════════════\033[0m\n";
echo "\033[32m  PASSED:  {$passed}\033[0m\n";
echo "\033[31m  FAILED:  {$failed}\033[0m\n";
echo "\033[33m  SKIPPED: {$skipped}\033[0m\n";
echo "\033[1m══════════════════════════════════════\033[0m\n";

if ($errors) {
    echo "\n\033[31mErrors:\033[0m\n";
    foreach ($errors as $e) echo "  - {$e}\n";
}

exit($failed > 0 ? 1 : 0);
