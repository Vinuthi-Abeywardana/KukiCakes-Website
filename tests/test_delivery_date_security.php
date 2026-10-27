<?php
/**
 * KúkiCakes — Delivery Date 48-Hour Security and Consistency Verification
 * 
 * Verifies that:
 * 1. Dates that are too early (past dates, today, tomorrow) are authoritatively rejected by the server (HTTP 422).
 * 2. Direct API requests that bypass frontend validation are strictly intercepted by the backend.
 * 3. The exact earliest permitted date (today + 2 days in Asia/Colombo) is accepted.
 * 4. Future dates (> 48 hours) are accepted.
 * 5. Timezone handling is strictly locked to Asia/Colombo (UTC+05:30).
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/db.php';

$baseUrl = 'http://localhost/KukiCakes-Website/api';
$passed = 0;
$failed = 0;

function assertCheck(bool $condition, string $title, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$title}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$title} {$details}\n";
        $failed++;
    }
}

function postJson(string $url, array $data, ?string $cookieFile = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode((string)$response, true) ?: $response];
}

echo "===============================================================\n";
echo " KúkiCakes — Delivery Date 48-Hour Security & Validation Suite\n";
echo "===============================================================\n\n";

$tzColombo = new DateTimeZone('Asia/Colombo');
$today = new DateTime('today', $tzColombo);
$todayStr = $today->format('Y-m-d');
$tomorrow = (clone $today)->modify('+1 day');
$tomorrowStr = $tomorrow->format('Y-m-d');
$earliest = (clone $today)->modify('+2 days');
$earliestStr = $earliest->format('Y-m-d');
$future = (clone $today)->modify('+5 days');
$futureStr = $future->format('Y-m-d');

echo "Timezone Environment:\n";
echo "  Current Server Local Time in Asia/Colombo: " . (new DateTime('now', $tzColombo))->format('Y-m-d H:i:s T') . "\n";
echo "  Calculated Today: {$todayStr}\n";
echo "  Calculated Tomorrow (+24h): {$tomorrowStr}\n";
echo "  Calculated Earliest Permitted (+48h): {$earliestStr}\n";
echo "  Calculated Future Date (+120h): {$futureStr}\n\n";

// Set up customer and session
$testEmail = 'qa_delivery_' . time() . '@kukicakes.test';
$cookieJar = tempnam(sys_get_temp_dir(), 'kuki_del_test_');

$regRes = postJson("{$baseUrl}/register.php", [
    'full_name'        => 'Delivery QA Tester',
    'email'            => $testEmail,
    'phone'            => '+94 77 111 2233',
    'password'         => 'TestPass123',
    'confirm_password' => 'TestPass123',
], $cookieJar);

$loginRes = postJson("{$baseUrl}/login.php", [
    'email'    => $testEmail,
    'password' => 'TestPass123',
], $cookieJar);

$custStmt = $pdo->prepare('SELECT customer_id FROM customer WHERE email = ?');
$custStmt->execute([$testEmail]);
$customerId = (int) $custStmt->fetchColumn();

// Add cart item
$addCartStmt = $pdo->prepare('INSERT INTO user_cart_items (customer_id, product_id, weight, flavor, note, quantity) VALUES (?, ?, ?, ?, ?, ?)');
$addCartStmt->execute([$customerId, 1, 'Standard', 'Vanilla', 'QA test note', 1]);

$basePayload = [
    'full_name'            => 'Delivery QA Tester',
    'email'                => $testEmail,
    'phone_no'             => '+94 77 111 2233',
    'delivery_address'     => '123 Galle Road, Colombo 03',
    'delivery_time_slot'   => 'Morning (8AM - 12PM)',
    'special_instructions' => 'QA advance notice test',
    'payment_method'       => 'cash',
];

echo "[1] Testing Delivery Dates That Are Too Early (Must Be Rejected)...\n";

// 1a: Far past date
$payloadPast = $basePayload;
$payloadPast['delivery_date'] = '2023-05-15';
$resPast = postJson("{$baseUrl}/create-order.php", $payloadPast, $cookieJar);
assertCheck($resPast['code'] === 422, "Past date 2023-05-15 rejected with HTTP 422");
assertCheck(isset($resPast['data']['errors']['delivery_date']), "Past date returns delivery_date error key");
assertCheck($resPast['data']['errors']['delivery_date'] === 'Delivery date cannot be in the past.', "Past date returns exact past date message");

// 1b: Today (0 hours notice)
$payloadToday = $basePayload;
$payloadToday['delivery_date'] = $todayStr;
$resToday = postJson("{$baseUrl}/create-order.php", $payloadToday, $cookieJar);
assertCheck($resToday['code'] === 422, "Today's date ({$todayStr}) rejected with HTTP 422");
assertCheck(stripos($resToday['data']['errors']['delivery_date'] ?? '', '48 hours') !== false, "Today's date explains 48-hour requirement");

// 1c: Tomorrow (+24 hours notice)
$payloadTomorrow = $basePayload;
$payloadTomorrow['delivery_date'] = $tomorrowStr;
$resTomorrow = postJson("{$baseUrl}/create-order.php", $payloadTomorrow, $cookieJar);
assertCheck($resTomorrow['code'] === 422, "Tomorrow's date ({$tomorrowStr}) rejected with HTTP 422");
assertCheck(stripos($resTomorrow['data']['errors']['delivery_date'] ?? '', '48 hours') !== false, "Tomorrow's date explains 48-hour requirement");
assertCheck(stripos($resTomorrow['data']['errors']['delivery_date'] ?? '', $earliest->format('d/m/Y')) !== false, "Error message states the earliest permitted date ({$earliest->format('d/m/Y')})");

echo "\n[2] Testing Request That Bypasses Frontend Validation...\n";
// Attacker bypasses HTML min attribute and JS validation with cURL directly
$bypassPayload = $basePayload;
$bypassPayload['delivery_date'] = $tomorrowStr; // 24h instead of 48h
$bypassRes = postJson("{$baseUrl}/create-order.php", $bypassPayload, $cookieJar);
assertCheck($bypassRes['code'] === 422, "Direct API bypass attempt rejected by server-side validation (HTTP 422)");
assertCheck($bypassRes['data']['success'] === false, "Server response reports success: false");

echo "\n[3] Testing Exact Earliest Permitted Date/Time (+48 Hours Notice)...\n";
$payloadEarliest = $basePayload;
$payloadEarliest['delivery_date'] = $earliestStr;
$payloadEarliest['delivery_time_slot'] = 'Morning (8AM - 12PM)';
$resEarliest = postJson("{$baseUrl}/create-order.php", $payloadEarliest, $cookieJar);
assertCheck($resEarliest['code'] === 201, "Earliest permitted date ({$earliestStr}) accepted with HTTP 201 Created");
assertCheck($resEarliest['data']['success'] === true, "Order successfully created for earliest permitted date");
$orderId1 = $resEarliest['data']['order']['order_id'] ?? null;
assertCheck(!empty($orderId1), "Server generated valid order_id: {$orderId1}");

echo "\n[4] Testing Valid Future Delivery Date (> 48 Hours Notice)...\n";
// Re-insert cart item for second order
$addCartStmt->execute([$customerId, 1, 'Standard', 'Chocolate', 'Future order', 1]);
$payloadFuture = $basePayload;
$payloadFuture['delivery_date'] = $futureStr;
$payloadFuture['delivery_time_slot'] = 'Evening (4PM - 8PM)';
$resFuture = postJson("{$baseUrl}/create-order.php", $payloadFuture, $cookieJar);
assertCheck($resFuture['code'] === 201, "Future date ({$futureStr}) accepted with HTTP 201 Created");
assertCheck($resFuture['data']['success'] === true, "Order successfully created for future date");
$orderId2 = $resFuture['data']['order']['order_id'] ?? null;
assertCheck(!empty($orderId2), "Server generated valid order_id: {$orderId2}");

echo "\n[5] Cleaning up test fixtures...\n";
if ($customerId > 0) {
    $pdo->prepare('DELETE FROM order_item WHERE order_id IN (SELECT order_id FROM orders WHERE customer_id = ?)')->execute([$customerId]);
    $pdo->prepare('DELETE FROM orders WHERE customer_id = ?')->execute([$customerId]);
    $pdo->prepare('DELETE FROM user_cart_items WHERE customer_id = ?')->execute([$customerId]);
    $pdo->prepare('DELETE FROM customer WHERE customer_id = ?')->execute([$customerId]);
}
if (file_exists($cookieJar)) @unlink($cookieJar);

echo "Cleanup complete.\n\n";
echo "===============================================================\n";
echo " Test Results: {$passed} Passed, {$failed} Failed\n";
echo "===============================================================\n";

if ($failed > 0) exit(1);
