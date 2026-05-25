<?php

/**
 * Race Condition Functional Test
 * ================================
 * Simulates a flash sale burst: N concurrent requests all try to buy a product
 * that only has STOCK_LIMIT units available.
 *
 * Pass criteria:
 *   - Exactly STOCK_LIMIT orders succeed (HTTP 201)
 *   - All remaining requests fail with 409 Conflict
 *   - inventory_count in the database ends at exactly 0
 *
 * Usage:
 *   php tests/RaceConditionTest.php [base_url] [product_id] [concurrent_requests]
 *
 * Example:
 *   php tests/RaceConditionTest.php http://localhost:8000 2 50
 */

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

$baseUrl     = $argv[1] ?? 'http://localhost:8000';
$productId   = (int) ($argv[2] ?? 1);   // Flash sale product from seeder
$totalRequests = (int) ($argv[3] ?? 50); // Simulate 50 concurrent buyers

$apiUrl      = "{$baseUrl}/api/v1/orders";
$productUrl  = "{$baseUrl}/api/v1/products/{$productId}";

// ---------------------------------------------------------------------------
// Helper: fetch current inventory (to derive expected success count)
// ---------------------------------------------------------------------------

function fetchInventory(string $url): int
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true]);
    $body = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($body, true);
    return (int) ($data['data']['inventory_count'] ?? 0);
}

// ---------------------------------------------------------------------------
// Step 1: Check initial stock
// ---------------------------------------------------------------------------

echo "=== Flash Sale Race Condition Test ===" . PHP_EOL;
echo "API:              {$apiUrl}" . PHP_EOL;
echo "Product ID:       {$productId}" . PHP_EOL;
echo "Concurrent reqs:  {$totalRequests}" . PHP_EOL;

$initialStock = fetchInventory($productUrl);
echo "Initial stock:    {$initialStock}" . PHP_EOL;

if ($initialStock === 0) {
    echo PHP_EOL . "[SKIP] Product is already out of stock. Re-seed the database and try again." . PHP_EOL;
    exit(1);
}

$expectedSuccess = min($initialStock, $totalRequests);
$expectedFail    = $totalRequests - $expectedSuccess;

echo "Expected success: {$expectedSuccess}" . PHP_EOL;
echo "Expected fail:    {$expectedFail}" . PHP_EOL;
echo PHP_EOL . "Firing {$totalRequests} concurrent requests..." . PHP_EOL . PHP_EOL;

// ---------------------------------------------------------------------------
// Step 2: Build curl_multi handle — all requests prepared simultaneously
// ---------------------------------------------------------------------------

$multiHandle = curl_multi_init();
$handles     = [];

for ($i = 1; $i <= $totalRequests; $i++) {
    $payload = json_encode([
        'customer_name'  => "Customer {$i}",
        'customer_email' => "customer{$i}@example.com",
        'items'          => [
            ['product_id' => $productId, 'quantity' => 1],
        ],
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);

    curl_multi_add_handle($multiHandle, $ch);
    $handles[$i] = $ch;
}

// ---------------------------------------------------------------------------
// Step 3: Execute all requests in parallel
// ---------------------------------------------------------------------------

$active = null;
do {
    curl_multi_exec($multiHandle, $active);
    curl_multi_select($multiHandle);
} while ($active > 0);

// ---------------------------------------------------------------------------
// Step 4: Collect results
// ---------------------------------------------------------------------------

$successCount = 0;
$failCount    = 0;
$statusCodes  = [];

foreach ($handles as $i => $ch) {
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $statusCodes[] = $httpCode;

    if ($httpCode === 201) {
        $successCount++;
    } else {
        $failCount++;
    }

    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);
}

curl_multi_close($multiHandle);

// ---------------------------------------------------------------------------
// Step 5: Verify final inventory
// ---------------------------------------------------------------------------

$finalStock = fetchInventory($productUrl);

// ---------------------------------------------------------------------------
// Step 6: Report
// ---------------------------------------------------------------------------

echo "Results:" . PHP_EOL;
echo "  201 Created:   {$successCount}" . PHP_EOL;
echo "  409 Conflict:  {$failCount}" . PHP_EOL;
echo "  Other:         " . ($totalRequests - $successCount - $failCount) . PHP_EOL;
echo "  Final stock:   {$finalStock}" . PHP_EOL;
echo PHP_EOL;

// ---------------------------------------------------------------------------
// Step 7: Assertions
// ---------------------------------------------------------------------------

$passed = true;

function assert_equal(string $label, mixed $expected, mixed $actual): bool
{
    $ok = $expected === $actual;
    $mark = $ok ? '✓' : '✗';
    echo "  [{$mark}] {$label}: expected {$expected}, got {$actual}" . PHP_EOL;
    return $ok;
}

$passed = assert_equal('Successful orders', $expectedSuccess, $successCount) && $passed;
$passed = assert_equal('Failed orders (409)', $expectedFail, $failCount)     && $passed;
$passed = assert_equal('Final inventory', 0, $finalStock)                    && $passed;

echo PHP_EOL;

if ($passed) {
    echo "✓ ALL TESTS PASSED — race condition handled correctly." . PHP_EOL;
    exit(0);
} else {
    echo "✗ TEST FAILED — race condition was NOT handled correctly." . PHP_EOL;
    echo "  If success > expected, inventory went negative (oversold)." . PHP_EOL;
    exit(1);
}