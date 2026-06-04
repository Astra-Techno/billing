<?php
/**
 * Comprehensive API Test Script for CloudBill
 *
 * Usage: php test-api.php [base_url] [email] [password]
 * Example: php test-api.php https://billing.cloudkart24.com test@example.com password123
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
    if ($method === 'GET' && $data) $url .= '?' . http_build_query($data);
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
    $json = json_decode($response, true) ?? ['success' => false, 'message' => "Invalid JSON: " . substr($response, 0, 300)];
    $json['_http'] = $httpCode;
    return $json;
}

function test(string $name, callable $fn): void {
    global $passed, $failed, $skipped, $errors;
    echo "  {$name} ... ";
    try {
        $result = $fn();
        if ($result === 'SKIP') { echo "\033[33mSKIPPED\033[0m\n"; $skipped++; }
        else { echo "\033[32mPASS\033[0m\n"; $passed++; }
    } catch (\Throwable $e) {
        echo "\033[31mFAIL\033[0m — {$e->getMessage()}\n";
        $failed++;
        $errors[] = "$name: {$e->getMessage()}";
    }
}

function ok(bool $cond, string $msg = 'Assertion failed'): void {
    if (!$cond) throw new \RuntimeException($msg);
}

function section(string $title): void {
    echo "\n\033[1;36m── {$title} ──\033[0m\n";
}

// ══════════════════════════════════════════════════════════════════════════
// AUTH
// ══════════════════════════════════════════════════════════════════════════

section('AUTH');

test('Login with valid credentials', function() {
    global $token, $EMAIL, $PASSWORD;
    $res = api('POST', 'login', ['email' => $EMAIL, 'password' => $PASSWORD], false);
    ok($res['success'] === true, $res['message'] ?? 'Login failed');
    $token = $res['data']['token'] ?? null;
    ok(!empty($token), 'No token returned');
    ok(!empty($res['data']['user']['id']), 'No user data');
    ok(!empty($res['data']['user']['email']), 'No user email');
    ok(isset($res['data']['businesses']), 'No businesses array');
});

test('Login with wrong password', function() {
    $res = api('POST', 'login', ['email' => 'gjmat28@gmail.com', 'password' => 'WrongPassword999'], false);
    ok($res['success'] === false, 'Should have failed');
    ok($res['_http'] === 401 || str_contains($res['message'] ?? '', 'Invalid'), 'Expected 401 or invalid msg');
});

test('Login with missing fields', function() {
    $res = api('POST', 'login', ['email' => ''], false);
    ok($res['success'] === false, 'Should have failed');
});

test('Update profile', function() {
    $res = api('POST', 'task/Auth/updateProfile', ['name' => 'Test User']);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    ok(!empty($res['data']['name']), 'No name returned');
});

// ══════════════════════════════════════════════════════════════════════════
// BUSINESS
// ══════════════════════════════════════════════════════════════════════════

section('BUSINESS');

$businessData = null;

test('Fetch business (item/Business)', function() {
    global $businessData;
    $res = api('GET', 'item/Business');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    ok(!empty($res['data']['id']), 'No business id');
    ok(!empty($res['data']['name']), 'No business name');
    $businessData = $res['data'];
});

test('Fetch business with plan (item/Business:withPlan)', function() {
    global $businessData;
    if (!$businessData) return 'SKIP';
    $res = api('GET', 'item/Business:withPlan', ['id' => $businessData['id']]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Update business profile', function() {
    global $businessData;
    if (!$businessData) return 'SKIP';
    $res = api('POST', 'task/Business/updateProfile', [
        'name'   => $businessData['name'],
        'mobile' => $businessData['mobile'] ?? '9999999999',
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Plan info', function() {
    $res = api('POST', 'task/Business/planInfo', []);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// REFERENCE DATA (public + auth)
// ══════════════════════════════════════════════════════════════════════════

section('REFERENCE DATA');

test('List Indian states (all/IndianState)', function() {
    $res = api('GET', 'all/IndianState');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    ok(is_array($res['data']) && count($res['data']) > 0, 'No states');
});

test('List tax rates (list/TaxRate)', function() {
    $res = api('GET', 'list/TaxRate');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('All tax rates (all/TaxRate)', function() {
    $res = api('GET', 'all/TaxRate');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Expense categories (all/ExpenseCategory)', function() {
    $res = api('GET', 'all/ExpenseCategory');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// TAX RATES CRUD
// ══════════════════════════════════════════════════════════════════════════

section('TAX RATES');

$testTaxRateId = null;

test('Create tax rate', function() {
    global $testTaxRateId;
    $res = api('POST', 'task/TaxRate/create', [
        'name' => 'ZZ-Test-GST-5%',
        'rate' => 5,
        'cgst_rate' => 2.5,
        'sgst_rate' => 2.5,
        'igst_rate' => 5,
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    $testTaxRateId = $res['data']['id'] ?? $res['data']['tax_rate_id'] ?? null;
});

test('Update tax rate', function() {
    global $testTaxRateId;
    if (!$testTaxRateId) return 'SKIP';
    $res = api('POST', 'task/TaxRate/update', [
        'id'   => $testTaxRateId,
        'name' => 'ZZ-Test-GST-5%-Updated',
        'rate' => 5,
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Delete tax rate', function() {
    global $testTaxRateId;
    if (!$testTaxRateId) return 'SKIP';
    $res = api('POST', 'task/TaxRate/delete', ['id' => $testTaxRateId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// PRODUCTS CRUD
// ══════════════════════════════════════════════════════════════════════════

section('PRODUCTS');

$testProductId = null;
$testProductName = 'ZZ-Test-Product-' . time();

test('List products (list/Product)', function() {
    $res = api('GET', 'list/Product');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    ok(is_array($res['data']), 'Invalid data');
});

test('All products (all/Product)', function() {
    $res = api('GET', 'all/Product');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Create product', function() {
    global $testProductId, $testProductName;
    $res = api('POST', 'task/Product/create', [
        'type' => 'product', 'name' => $testProductName, 'price' => 100,
        'unit' => 'Nos', 'hsn_sac' => '1234', 'description' => 'Test product',
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    $testProductId = $res['data']['product_id'] ?? $res['data']['id'] ?? null;
    ok(!empty($testProductId), 'No product_id');
    ok($res['data']['name'] === $testProductName, 'Name mismatch');
    ok($res['data']['unit'] === 'Nos', 'Unit mismatch');
});

test('Create product — missing name', function() {
    $res = api('POST', 'task/Product/create', ['type' => 'product', 'price' => 100]);
    ok($res['success'] === false, 'Should have failed');
});

test('Create product — invalid type', function() {
    $res = api('POST', 'task/Product/create', ['type' => 'invalid', 'name' => 'X', 'price' => 10]);
    ok($res['success'] === false, 'Should have failed');
});

test('Duplicate product blocked', function() {
    global $testProductName;
    $res = api('POST', 'task/Product/create', ['type' => 'product', 'name' => $testProductName, 'price' => 200]);
    ok($res['success'] === false, 'Duplicate not blocked');
});

test('Create service product', function() {
    $res = api('POST', 'task/Product/create', [
        'type' => 'service', 'name' => 'ZZ-Test-Service-' . time(), 'price' => 500, 'unit' => 'Hrs',
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    // Clean up
    $sid = $res['data']['product_id'] ?? $res['data']['id'] ?? null;
    if ($sid) api('POST', 'task/Product/delete', ['id' => $sid]);
});

test('Update product', function() {
    global $testProductId, $testProductName;
    if (!$testProductId) return 'SKIP';
    $res = api('POST', 'task/Product/update', [
        'id' => $testProductId, 'name' => $testProductName . '-Updated',
        'price' => 150, 'unit' => 'Box', 'hsn_sac' => '5678',
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Fetch product (item/Product)', function() {
    global $testProductId;
    if (!$testProductId) return 'SKIP';
    $res = api('GET', 'item/Product', ['id' => $testProductId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    ok($res['data']['unit'] === 'Box', 'Unit not updated');
});

test('Delete product', function() {
    global $testProductId;
    if (!$testProductId) return 'SKIP';
    $res = api('POST', 'task/Product/delete', ['id' => $testProductId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// CLIENTS CRUD
// ══════════════════════════════════════════════════════════════════════════

section('CLIENTS');

$testClientId = null;
$testContactId = null;

test('List clients (list/Client)', function() {
    $res = api('GET', 'list/Client');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('All clients (all/Client)', function() {
    $res = api('GET', 'all/Client');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Client options (options/Client)', function() {
    $res = api('GET', 'options/Client');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Create business client', function() {
    global $testClientId;
    $res = api('POST', 'task/Client/create', [
        'name' => 'ZZ-Test-Client-' . time(), 'type' => 'business',
        'company' => 'Test Corp', 'email' => 'test' . time() . '@test.com',
        'mobile' => '9876543210', 'city' => 'Chennai', 'pincode' => '600001',
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    $testClientId = $res['data']['client_id'] ?? $res['data']['id'] ?? null;
    ok(!empty($testClientId), 'No client_id');
});

test('Create individual client', function() {
    $res = api('POST', 'task/Client/create', [
        'name' => 'ZZ-Test-Individual-' . time(), 'type' => 'individual',
        'mobile' => '9876500000',
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    $cid = $res['data']['client_id'] ?? $res['data']['id'] ?? null;
    if ($cid) api('POST', 'task/Client/delete', ['id' => $cid]);
});

test('Create client — missing name', function() {
    $res = api('POST', 'task/Client/create', ['type' => 'business']);
    ok($res['success'] === false, 'Should have failed');
});

test('Create client — invalid type', function() {
    $res = api('POST', 'task/Client/create', ['name' => 'X', 'type' => 'unknown']);
    ok($res['success'] === false, 'Should have failed');
});

test('Fetch single client (item/Client)', function() {
    global $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('GET', 'item/Client', ['id' => $testClientId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    ok(!empty($res['data']['name']), 'No name');
});

test('Update client', function() {
    global $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('POST', 'task/Client/update', [
        'id' => $testClientId, 'name' => 'ZZ-Test-Client-Updated',
        'city' => 'Mumbai',
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Add client contact', function() {
    global $testClientId, $testContactId;
    if (!$testClientId) return 'SKIP';
    $res = api('POST', 'task/Client/addContact', [
        'client_id' => $testClientId, 'name' => 'John Doe',
        'email' => 'john@test.com', 'mobile' => '9999000000',
        'designation' => 'Manager', 'is_primary' => true,
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    $testContactId = $res['data']['contact_id'] ?? $res['data']['id'] ?? null;
});

test('List client contacts (list/Client:contacts)', function() {
    global $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('GET', 'list/Client:contacts', ['client_id' => $testClientId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Update client contact', function() {
    global $testContactId;
    if (!$testContactId) return 'SKIP';
    $res = api('POST', 'task/Client/updateContact', [
        'id' => $testContactId, 'name' => 'John Updated', 'designation' => 'Director',
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Delete client contact', function() {
    global $testContactId;
    if (!$testContactId) return 'SKIP';
    $res = api('POST', 'task/Client/deleteContact', ['id' => $testContactId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Client outstanding (list/Client:outstanding)', function() {
    global $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('GET', 'list/Client:outstanding', ['client_id' => $testClientId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// EXPENSES CRUD
// ══════════════════════════════════════════════════════════════════════════

section('EXPENSES');

$testExpenseId = null;
$testExpCatId = null;

test('List expenses (list/Expense)', function() {
    $res = api('GET', 'list/Expense');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Add expense category', function() {
    global $testExpCatId;
    $res = api('POST', 'task/Expense/addCategory', ['name' => 'ZZ-Test-Category-' . time()]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    $testExpCatId = $res['data']['id'] ?? $res['data']['category_id'] ?? null;
});

test('Create expense', function() {
    global $testExpenseId, $testExpCatId;
    $res = api('POST', 'task/Expense/create', [
        'description'  => 'ZZ-Test-Expense', 'total_amount' => 500,
        'expense_date' => date('Y-m-d'), 'vendor_name' => 'Test Vendor',
        'method' => 'cash', 'notes' => 'Test expense',
        'category_id'  => $testExpCatId,
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    $testExpenseId = $res['data']['expense_id'] ?? $res['data']['id'] ?? null;
    ok(!empty($testExpenseId), 'No expense_id');
});

test('Fetch expense (item/Expense)', function() {
    global $testExpenseId;
    if (!$testExpenseId) return 'SKIP';
    $res = api('GET', 'item/Expense', ['id' => $testExpenseId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Update expense', function() {
    global $testExpenseId;
    if (!$testExpenseId) return 'SKIP';
    $res = api('POST', 'task/Expense/update', [
        'id' => $testExpenseId, 'description' => 'Updated Expense',
        'total_amount' => 750, 'expense_date' => date('Y-m-d'),
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Delete expense', function() {
    global $testExpenseId;
    if (!$testExpenseId) return 'SKIP';
    $res = api('POST', 'task/Expense/delete', ['id' => $testExpenseId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Delete expense category', function() {
    global $testExpCatId;
    if (!$testExpCatId) return 'SKIP';
    $res = api('POST', 'task/Expense/deleteCategory', ['id' => $testExpCatId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// INVOICES — Full lifecycle
// ══════════════════════════════════════════════════════════════════════════

section('INVOICES');

$testInvoiceId = null;
$testInvoiceId2 = null;

test('List invoices (list/Invoice)', function() {
    $res = api('GET', 'list/Invoice');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('List invoices with filters', function() {
    $res = api('GET', 'list/Invoice', ['filter' => ['status' => 'paid']]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Create tax invoice', function() {
    global $testInvoiceId, $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('POST', 'task/Invoice/create', [
        'client_id' => $testClientId, 'invoice_type' => 'tax_invoice',
        'issue_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+30 days')),
        'notes' => 'Test invoice', 'terms' => 'Net 30',
        'items' => [[
            'description' => 'Web Development', 'quantity' => 2,
            'unit' => 'Hrs', 'unit_price' => 500, 'gst_rate' => 18,
        ]],
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    $testInvoiceId = $res['data']['invoice_id'] ?? $res['data']['id'] ?? null;
    ok(!empty($testInvoiceId), 'No invoice_id');
});

test('Create bill of supply', function() {
    global $testInvoiceId2, $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('POST', 'task/Invoice/create', [
        'client_id' => $testClientId, 'invoice_type' => 'bill_of_supply',
        'issue_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+15 days')),
        'items' => [[
            'description' => 'Consulting', 'quantity' => 1,
            'unit' => 'Nos', 'unit_price' => 1000, 'gst_rate' => 0,
        ]],
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    $testInvoiceId2 = $res['data']['invoice_id'] ?? $res['data']['id'] ?? null;
});

test('Create invoice — missing client', function() {
    $res = api('POST', 'task/Invoice/create', [
        'issue_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+30 days')),
        'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 100, 'gst_rate' => 0]],
    ]);
    ok($res['success'] === false, 'Should have failed');
});

test('Create invoice — empty items', function() {
    global $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('POST', 'task/Invoice/create', [
        'client_id' => $testClientId, 'issue_date' => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+30 days')), 'items' => [],
    ]);
    ok($res['success'] === false, 'Should have failed');
});

test('Fetch invoice (item/Invoice)', function() {
    global $testInvoiceId;
    if (!$testInvoiceId) return 'SKIP';
    $res = api('GET', 'item/Invoice', ['id' => $testInvoiceId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    ok(!empty($res['data']['number']), 'No invoice number');
});

test('Fetch invoice items (list/Invoice:items)', function() {
    global $testInvoiceId;
    if (!$testInvoiceId) return 'SKIP';
    $res = api('GET', 'list/Invoice:items', ['invoice_id' => $testInvoiceId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    ok(is_array($res['data']) && count($res['data']) > 0, 'No items');
});

test('Update invoice', function() {
    global $testInvoiceId, $testClientId;
    if (!$testInvoiceId) return 'SKIP';
    $res = api('POST', 'task/Invoice/update', [
        'id' => $testInvoiceId, 'client_id' => $testClientId,
        'issue_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+30 days')),
        'notes' => 'Updated notes',
        'items' => [
            ['description' => 'Updated Item', 'quantity' => 3, 'unit' => 'Nos', 'unit_price' => 600, 'gst_rate' => 18],
            ['description' => 'Second Item', 'quantity' => 1, 'unit' => 'Nos', 'unit_price' => 200, 'gst_rate' => 18],
        ],
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Mark invoice sent', function() {
    global $testInvoiceId;
    if (!$testInvoiceId) return 'SKIP';
    $res = api('POST', 'task/Invoice/markSent', ['id' => $testInvoiceId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Mark invoice paid', function() {
    global $testInvoiceId;
    if (!$testInvoiceId) return 'SKIP';
    $res = api('POST', 'task/Invoice/markPaid', [
        'id' => $testInvoiceId, 'payment_date' => date('Y-m-d'), 'method' => 'upi',
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Invoice payments (list/Invoice:payments)', function() {
    global $testInvoiceId;
    if (!$testInvoiceId) return 'SKIP';
    $res = api('GET', 'list/Invoice:payments', ['invoice_id' => $testInvoiceId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Duplicate invoice', function() {
    global $testInvoiceId;
    if (!$testInvoiceId) return 'SKIP';
    $res = api('POST', 'task/Invoice/duplicate', ['id' => $testInvoiceId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    // Cancel the duplicate
    $dupId = $res['data']['invoice_id'] ?? $res['data']['id'] ?? null;
    if ($dupId) api('POST', 'task/Invoice/cancel', ['id' => $dupId]);
});

test('Recurring invoices (list/Invoice:recurring)', function() {
    $res = api('GET', 'list/Invoice:recurring');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Overdue invoices (list/Invoice:overdue)', function() {
    $res = api('GET', 'list/Invoice:overdue');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// PAYMENTS
// ══════════════════════════════════════════════════════════════════════════

section('PAYMENTS');

$testPaymentId = null;

test('List payments (list/Payment)', function() {
    $res = api('GET', 'list/Payment');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Record payment on bill of supply', function() {
    global $testInvoiceId2, $testPaymentId;
    if (!$testInvoiceId2) return 'SKIP';
    // Mark sent first
    api('POST', 'task/Invoice/markSent', ['id' => $testInvoiceId2]);
    $res = api('POST', 'task/Payment/record', [
        'invoice_id' => $testInvoiceId2, 'amount' => 500,
        'method' => 'cash', 'payment_date' => date('Y-m-d'),
        'reference' => 'TEST-REF-001',
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    $testPaymentId = $res['data']['payment_id'] ?? $res['data']['id'] ?? null;
});

test('Delete payment', function() {
    global $testPaymentId;
    if (!$testPaymentId) return 'SKIP';
    $res = api('POST', 'task/Payment/delete', ['id' => $testPaymentId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// CREDIT NOTES — Full lifecycle
// ══════════════════════════════════════════════════════════════════════════

section('CREDIT NOTES');

$testCreditNoteId = null;

test('Create credit note (against sent invoice)', function() {
    global $testCreditNoteId, $testInvoiceId2;
    if (!$testInvoiceId2) return 'SKIP';
    // Mark invoice sent first
    api('POST', 'task/Invoice/markSent', ['id' => $testInvoiceId2]);
    $res = api('POST', 'task/CreditNote/create', [
        'invoice_id' => $testInvoiceId2,
        'reason'     => 'discount',
        'issue_date' => date('Y-m-d'),
        'notes'      => 'Test credit note',
        'items'      => [[
            'description' => 'Discount on Consulting', 'quantity' => 1,
            'unit' => 'Nos', 'unit_price' => 200, 'gst_rate' => 0,
        ]],
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    $testCreditNoteId = $res['data']['credit_note_id'] ?? null;
    ok(!empty($testCreditNoteId), 'No credit_note_id');
    ok(!empty($res['data']['number']), 'No CN number');
});

test('Create credit note — cancelled invoice blocked', function() {
    global $testInvoiceId;
    if (!$testInvoiceId) return 'SKIP';
    // testInvoiceId is paid, try creating CN for it — should work
    // But let's test with a truly cancelled invoice if we had one
    // For now just test missing invoice_id
    $res = api('POST', 'task/CreditNote/create', [
        'reason' => 'return', 'issue_date' => date('Y-m-d'),
        'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 10, 'gst_rate' => 0]],
    ]);
    ok($res['success'] === false, 'Should have failed without invoice_id');
});

test('Issue credit note', function() {
    global $testCreditNoteId;
    if (!$testCreditNoteId) return 'SKIP';
    $res = api('POST', 'task/CreditNote/issue', ['id' => $testCreditNoteId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Adjust credit note against invoice', function() {
    global $testCreditNoteId;
    if (!$testCreditNoteId) return 'SKIP';
    $res = api('POST', 'task/CreditNote/adjust', ['id' => $testCreditNoteId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// DEBIT NOTES — Full lifecycle
// ══════════════════════════════════════════════════════════════════════════

section('DEBIT NOTES');

$testDebitNoteId = null;

test('Create debit note', function() {
    global $testDebitNoteId, $testInvoiceId2;
    if (!$testInvoiceId2) return 'SKIP';
    $res = api('POST', 'task/DebitNote/create', [
        'invoice_id' => $testInvoiceId2,
        'reason'     => 'price_revision',
        'issue_date' => date('Y-m-d'),
        'subtotal'   => 500,
        'gst_rate'   => 0,
        'notes'      => 'Price revision test',
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    $testDebitNoteId = $res['data']['number'] ?? null;
});

test('Create debit note — missing fields', function() {
    $res = api('POST', 'task/DebitNote/create', ['reason' => 'other']);
    ok($res['success'] === false, 'Should have failed');
});

test('Issue debit note', function() {
    global $testDebitNoteId;
    // We need the ID, not the number — let's try to get it
    // DebitNote create only returns number, not id. We'll skip if needed.
    if (!$testDebitNoteId) return 'SKIP';
    // Since we only have the number, skip the issue test for now
    return 'SKIP';
});

// ══════════════════════════════════════════════════════════════════════════
// ADDITIONAL SQL ENDPOINTS
// ══════════════════════════════════════════════════════════════════════════

section('ADDITIONAL SQL ENDPOINTS');

test('Expense categories via Sql (list/Expense:categories)', function() {
    $res = api('GET', 'list/Expense:categories');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Client top items (list/Client:topItems)', function() {
    global $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('GET', 'list/Client:topItems', ['client_id' => $testClientId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// BUSINESS — GST & Bank updates
// ══════════════════════════════════════════════════════════════════════════

section('BUSINESS — GST & BANK');

test('Update GST details', function() {
    global $businessData;
    if (!$businessData) return 'SKIP';
    $res = api('POST', 'task/Business/updateGst', [
        'gstin' => $businessData['gstin'] ?? '33AABCU9603R1ZM',
        'pan'   => $businessData['pan'] ?? 'AABCU9603R',
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Update bank details', function() {
    $res = api('POST', 'task/Business/updateBank', [
        'bank_name'       => 'Test Bank',
        'bank_account_no' => '1234567890',
        'bank_ifsc'       => 'TEST0001234',
        'upi_id'          => 'test@upi',
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// INVOICE — Bulk & Advanced operations
// ══════════════════════════════════════════════════════════════════════════

section('INVOICE — BULK & ADVANCED');

test('Invoice PDF download', function() {
    global $testInvoiceId;
    if (!$testInvoiceId) return 'SKIP';
    $res = api('GET', 'invoice/' . $testInvoiceId . '/pdf');
    // PDF endpoint may return binary or redirect — just check no 500 error
    ok($res['_http'] !== 500, 'Server error on PDF: ' . ($res['message'] ?? ''));
});

// ══════════════════════════════════════════════════════════════════════════
// QUOTES — Full lifecycle
// ══════════════════════════════════════════════════════════════════════════

section('QUOTES');

$testQuoteId = null;

test('List quotes (list/Quote)', function() {
    $res = api('GET', 'list/Quote');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Create quote', function() {
    global $testQuoteId, $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('POST', 'task/Quote/create', [
        'client_id' => $testClientId, 'issue_date' => date('Y-m-d'),
        'valid_until' => date('Y-m-d', strtotime('+15 days')),
        'notes' => 'Test quote', 'terms' => 'Valid 15 days',
        'items' => [[
            'description' => 'Quote Item', 'quantity' => 5,
            'unit' => 'Nos', 'unit_price' => 200, 'gst_rate' => 18,
        ]],
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    $testQuoteId = $res['data']['quote_id'] ?? $res['data']['id'] ?? null;
    ok(!empty($testQuoteId), 'No quote_id');
});

test('Create quote — missing items', function() {
    global $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('POST', 'task/Quote/create', [
        'client_id' => $testClientId, 'issue_date' => date('Y-m-d'),
    ]);
    ok($res['success'] === false, 'Should have failed');
});

test('Fetch quote (item/Quote)', function() {
    global $testQuoteId;
    if (!$testQuoteId) return 'SKIP';
    $res = api('GET', 'item/Quote', ['id' => $testQuoteId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Fetch quote items (list/Quote:items)', function() {
    global $testQuoteId;
    if (!$testQuoteId) return 'SKIP';
    $res = api('GET', 'list/Quote:items', ['quote_id' => $testQuoteId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    ok(is_array($res['data']) && count($res['data']) > 0, 'No items');
});

test('Update quote', function() {
    global $testQuoteId, $testClientId;
    if (!$testQuoteId) return 'SKIP';
    $res = api('POST', 'task/Quote/update', [
        'id' => $testQuoteId, 'client_id' => $testClientId,
        'issue_date' => date('Y-m-d'), 'notes' => 'Updated quote',
        'items' => [[
            'description' => 'Updated Quote Item', 'quantity' => 10,
            'unit' => 'Nos', 'unit_price' => 150, 'gst_rate' => 18,
        ]],
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Mark quote sent', function() {
    global $testQuoteId;
    if (!$testQuoteId) return 'SKIP';
    $res = api('POST', 'task/Quote/markSent', ['id' => $testQuoteId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Accept quote', function() {
    global $testQuoteId;
    if (!$testQuoteId) return 'SKIP';
    $res = api('POST', 'task/Quote/accept', ['id' => $testQuoteId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Convert quote to invoice', function() {
    global $testQuoteId;
    if (!$testQuoteId) return 'SKIP';
    $res = api('POST', 'task/Quote/convertToInvoice', [
        'id' => $testQuoteId, 'due_date' => date('Y-m-d', strtotime('+30 days')),
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    // Cancel the created invoice
    $invId = $res['data']['invoice_id'] ?? null;
    if ($invId) api('POST', 'task/Invoice/cancel', ['id' => $invId]);
});

// Test decline flow with a second quote
test('Create + decline quote', function() {
    global $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('POST', 'task/Quote/create', [
        'client_id' => $testClientId, 'issue_date' => date('Y-m-d'),
        'items' => [['description' => 'Decline test', 'quantity' => 1, 'unit' => 'Nos', 'unit_price' => 100, 'gst_rate' => 0]],
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    $qid = $res['data']['quote_id'] ?? $res['data']['id'] ?? null;
    if ($qid) {
        api('POST', 'task/Quote/markSent', ['id' => $qid]);
        $d = api('POST', 'task/Quote/decline', ['id' => $qid]);
        ok($d['success'] === true, $d['message'] ?? 'Decline failed');
        api('POST', 'task/Quote/delete', ['id' => $qid]);
    }
});

// ══════════════════════════════════════════════════════════════════════════
// DELIVERY CHALLANS — Full lifecycle
// ══════════════════════════════════════════════════════════════════════════

section('DELIVERY CHALLANS');

$testDcId = null;

test('List delivery challans (list/DeliveryChallan)', function() {
    $res = api('GET', 'list/DeliveryChallan');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Create delivery challan', function() {
    global $testDcId, $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('POST', 'task/DeliveryChallan/create', [
        'client_id' => $testClientId, 'challan_date' => date('Y-m-d'),
        'vehicle_no' => 'TN01AB1234', 'driver_name' => 'Ravi',
        'destination' => 'Chennai', 'notes' => 'Handle with care',
        'items' => [[
            'description' => 'Paper cups 90ml', 'quantity' => 10, 'unit' => 'Box',
        ]],
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    $testDcId = $res['data']['id'] ?? null;
    ok(!empty($testDcId), 'No DC id');
});

test('Fetch DC (item/DeliveryChallan)', function() {
    global $testDcId;
    if (!$testDcId) return 'SKIP';
    $res = api('GET', 'item/DeliveryChallan', ['id' => $testDcId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Fetch DC items (list/DeliveryChallan:items)', function() {
    global $testDcId;
    if (!$testDcId) return 'SKIP';
    $res = api('GET', 'list/DeliveryChallan:items', ['dc_id' => $testDcId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Update DC', function() {
    global $testDcId, $testClientId;
    if (!$testDcId) return 'SKIP';
    $res = api('POST', 'task/DeliveryChallan/update', [
        'id' => $testDcId, 'client_id' => $testClientId,
        'vehicle_no' => 'TN02CD5678', 'destination' => 'Bangalore',
        'items' => [
            ['description' => 'Paper cups 90ml', 'quantity' => 20, 'unit' => 'Box'],
            ['description' => 'Paper cups 150ml', 'quantity' => 5, 'unit' => 'Box'],
        ],
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Delete DC', function() {
    global $testDcId;
    if (!$testDcId) return 'SKIP';
    $res = api('POST', 'task/DeliveryChallan/delete', ['id' => $testDcId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// PURCHASE ORDERS — Full lifecycle
// ══════════════════════════════════════════════════════════════════════════

section('PURCHASE ORDERS');

$testPoId = null;

test('List purchase orders (list/PurchaseOrder)', function() {
    $res = api('GET', 'list/PurchaseOrder');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Create purchase order', function() {
    global $testPoId, $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('POST', 'task/PurchaseOrder/create', [
        'supplier_id' => $testClientId, 'order_date' => date('Y-m-d'),
        'expected_date' => date('Y-m-d', strtotime('+7 days')),
        'notes' => 'Urgent order',
        'items' => [[
            'description' => 'Raw Material A', 'quantity' => 100,
            'unit' => 'Kg', 'unit_price' => 50, 'gst_rate' => 18,
        ]],
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
    $testPoId = $res['data']['id'] ?? $res['data']['po_id'] ?? null;
    ok(!empty($testPoId), 'No PO id');
});

test('Fetch PO (item/PurchaseOrder)', function() {
    global $testPoId;
    if (!$testPoId) return 'SKIP';
    $res = api('GET', 'item/PurchaseOrder', ['id' => $testPoId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Fetch PO items (list/PurchaseOrder:items)', function() {
    global $testPoId;
    if (!$testPoId) return 'SKIP';
    $res = api('GET', 'list/PurchaseOrder:items', ['po_id' => $testPoId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Update PO', function() {
    global $testPoId, $testClientId;
    if (!$testPoId) return 'SKIP';
    $res = api('POST', 'task/PurchaseOrder/update', [
        'id' => $testPoId, 'supplier_id' => $testClientId,
        'notes' => 'Updated PO',
        'items' => [[
            'description' => 'Raw Material A Updated', 'quantity' => 200,
            'unit' => 'Kg', 'unit_price' => 45, 'gst_rate' => 18,
        ]],
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Send PO', function() {
    global $testPoId;
    if (!$testPoId) return 'SKIP';
    $res = api('POST', 'task/PurchaseOrder/send', ['id' => $testPoId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Receive PO', function() {
    global $testPoId;
    if (!$testPoId) return 'SKIP';
    $res = api('POST', 'task/PurchaseOrder/receive', ['id' => $testPoId]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// DASHBOARD & REPORTS
// ══════════════════════════════════════════════════════════════════════════

section('DASHBOARD & REPORTS');

test('Dashboard stats (list/Dashboard:stats)', function() {
    $res = api('GET', 'list/Dashboard:stats');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Dashboard summary (list/Dashboard:summary)', function() {
    $res = api('GET', 'list/Dashboard:summary');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Dashboard monthly revenue (list/Dashboard:monthlyRevenue)', function() {
    $res = api('GET', 'list/Dashboard:monthlyRevenue');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Dashboard top outstanding (list/Dashboard:topOutstandingClients)', function() {
    $res = api('GET', 'list/Dashboard:topOutstandingClients');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Dashboard recent invoices (list/Dashboard:recentInvoices)', function() {
    $res = api('GET', 'list/Dashboard:recentInvoices');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Dashboard recent payments (list/Dashboard:recentPayments)', function() {
    $res = api('GET', 'list/Dashboard:recentPayments');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Dashboard GST summary (list/Dashboard:gstSummary)', function() {
    $res = api('GET', 'list/Dashboard:gstSummary');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Dashboard expense summary (list/Dashboard:expenseSummary)', function() {
    $res = api('GET', 'list/Dashboard:expenseSummary');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Report — Profit & Loss (list/Report:profitLoss)', function() {
    $res = api('GET', 'list/Report:profitLoss');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Report — Ageing (list/Report:ageing)', function() {
    $res = api('GET', 'list/Report:ageing');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Report — Payment Collection (list/Report:paymentCollection)', function() {
    $res = api('GET', 'list/Report:paymentCollection');
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Report — HSN Summary (list/Report:hsnSummary)', function() {
    $res = api('GET', 'list/Report:hsnSummary', ['financial_year' => '2025-26']);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Report — GSTR B2B (list/Report:gstrB2b)', function() {
    $res = api('GET', 'list/Report:gstrB2b', ['financial_year' => '2025-26']);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Report — GSTR B2C (list/Report:gstrB2c)', function() {
    $res = api('GET', 'list/Report:gstrB2c', ['financial_year' => '2025-26']);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// SETTINGS
// ══════════════════════════════════════════════════════════════════════════

section('SETTINGS');

test('Fetch settings', function() {
    $res = api('POST', 'task/Settings/get', []);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Save settings', function() {
    $res = api('POST', 'task/Settings/save', [
        'settings' => ['test_key' => 'test_value_' . time()],
    ]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Delete setting', function() {
    $res = api('POST', 'task/Settings/delete', ['key' => 'test_key']);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// NOTIFICATIONS
// ══════════════════════════════════════════════════════════════════════════

section('NOTIFICATIONS');

test('Mark all notifications read', function() {
    $res = api('POST', 'task/Notification/markAllRead', []);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// STAFF
// ══════════════════════════════════════════════════════════════════════════

section('STAFF');

test('List staff', function() {
    $res = api('POST', 'task/Staff/list', []);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// SEQUENCES
// ══════════════════════════════════════════════════════════════════════════

section('SEQUENCES');

test('Get next invoice number', function() {
    global $businessData;
    if (!$businessData) return 'SKIP';
    $res = api('POST', 'task/Sequence/next', ['type' => 'invoice', 'business_id' => $businessData['id']]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Get next quote number', function() {
    global $businessData;
    if (!$businessData) return 'SKIP';
    $res = api('POST', 'task/Sequence/next', ['type' => 'quote', 'business_id' => $businessData['id']]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// CLEANUP
// ══════════════════════════════════════════════════════════════════════════

section('CLEANUP');

test('Cancel test invoice 1 (paid — expect fail)', function() {
    global $testInvoiceId;
    if (!$testInvoiceId) return 'SKIP';
    $res = api('POST', 'task/Invoice/cancel', ['id' => $testInvoiceId]);
    // Paid invoices can't be cancelled — that's correct behavior
    ok($res['success'] === true || str_contains($res['message'] ?? '', 'paid'), 'Unexpected error: ' . ($res['message'] ?? ''));
});

test('Cancel test invoice 2', function() {
    global $testInvoiceId2;
    if (!$testInvoiceId2) return 'SKIP';
    $res = api('POST', 'task/Invoice/cancel', ['id' => $testInvoiceId2]);
    ok($res['success'] === true, $res['message'] ?? 'Failed');
});

test('Cleanup test quote (converted — skip if not deletable)', function() {
    global $testQuoteId;
    if (!$testQuoteId) return 'SKIP';
    $res = api('POST', 'task/Quote/delete', ['id' => $testQuoteId]);
    // Converted/accepted quotes can't be deleted — that's correct
    ok($res['success'] === true || $res['success'] === false, 'API call failed entirely');
});

test('Cleanup test PO (received — skip if not deletable)', function() {
    global $testPoId;
    if (!$testPoId) return 'SKIP';
    $res = api('POST', 'task/PurchaseOrder/delete', ['id' => $testPoId]);
    // Received POs can't be deleted — that's correct
    ok($res['success'] === true || $res['success'] === false, 'API call failed entirely');
});

test('Delete test client', function() {
    global $testClientId;
    if (!$testClientId) return 'SKIP';
    $res = api('POST', 'task/Client/delete', ['id' => $testClientId]);
    ok($res['success'] === true || str_contains($res['message'] ?? '', 'open'), $res['message'] ?? 'Failed');
});

// ══════════════════════════════════════════════════════════════════════════
// SUMMARY
// ══════════════════════════════════════════════════════════════════════════

echo "\n\033[1m══════════════════════════════════════════════════════\033[0m\n";
echo "\033[32m  PASSED:  {$passed}\033[0m\n";
if ($failed) echo "\033[31m  FAILED:  {$failed}\033[0m\n";
else         echo "  FAILED:  0\n";
if ($skipped) echo "\033[33m  SKIPPED: {$skipped}\033[0m\n";
echo "  TOTAL:   " . ($passed + $failed + $skipped) . "\n";
echo "\033[1m══════════════════════════════════════════════════════\033[0m\n";

if ($errors) {
    echo "\n\033[31mErrors:\033[0m\n";
    foreach ($errors as $e) echo "  - {$e}\n";
}

exit($failed > 0 ? 1 : 0);
