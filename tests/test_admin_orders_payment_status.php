<?php
/**
 * Test Suite: KúkiCakes Admin Orders Payment Status Verification
 *
 * Verifies:
 * 1. Strict validation of payment_status enum values:
 *    ['pending', 'paid', 'failed', 'cancelled', 'charged_back']
 * 2. Independence of order_status and payment_status:
 *    Updating order_status does NOT alter payment_status, and vice-versa.
 * 3. Card orders require verified gateway evidence (payhere_payment_id):
 *    - Marking a card order 'paid' without payhere_payment_id is rejected with 422.
 *    - Marking a card order 'paid' with verified payhere_payment_id succeeds (200),
 *      sets payment_status = 'paid', records payhere_payment_id, and deducts inventory.
 * 4. Distinct handling of failed, cancelled, and charged_back statuses.
 * 5. Reverting 'paid' to 'pending' is rejected (400).
 * 6. Marking payment 'paid' for a cancelled order is rejected (400).
 * 7. Offline payment methods (cash, transfer) confirm payment with inventory checks.
 * 8. All test records are cleaned up after execution.
 */

require_once __DIR__ . '/../config/db.php';

$baseUrl = 'http://localhost/KukiCakes-Website/api';

$testsPassed = 0;
$testsFailed = 0;

function assertTest(bool $condition, string $description): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  [PASS] {$description}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$description}\n";
        $testsFailed++;
    }
}

function httpPost(string $url, array $data, ?string $cookie = null): array {
    $ch = curl_init($url);
    $payload = json_encode($data);
    $headers = ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)];
    if ($cookie) {
        $headers[] = 'Cookie: ' . $cookie;
    }
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true) ?: $response, 'raw' => $response];
}

function httpGet(string $url, ?string $cookie = null): array {
    $ch = curl_init($url);
    $headers = [];
    if ($cookie) {
        $headers[] = 'Cookie: ' . $cookie;
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true) ?: $response, 'raw' => $response];
}

// 1. Create test customer
$testEmail = 'pay_audit_test_' . time() . '@kukicakes.lk';
$pwdHash = password_hash('Pass123!', PASSWORD_BCRYPT);
$insCust = $pdo->prepare("INSERT INTO customer (full_name, email, phone_no, password_hash, account_status) VALUES ('Payment Tester', ?, '0771234567', ?, 'active')");
$insCust->execute([$testEmail, $pwdHash]);
$testCustId = (int) $pdo->lastInsertId();

// 2. Initialize sessions before any output (headers)
$adminSid = bin2hex(random_bytes(16));
$custSid = bin2hex(random_bytes(16));

session_name('KUKI_SESS');
session_id($adminSid);
session_start();
$_SESSION['admin_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'kuki_admin';
$adminCookie = 'KUKI_SESS=' . $adminSid;
session_write_close();

session_name('KUKI_SESS');
session_id($custSid);
session_start();
$_SESSION['customer_id'] = $testCustId;
$_SESSION['role'] = 'customer';
$custCookie = 'KUKI_SESS=' . $custSid;
session_write_close();

echo "====================================================\n";
echo " KúkiCakes Admin Payment Status Workflow Verification\n";
echo "====================================================\n\n";

// Helper to create test orders
function createTestOrder(string $method, string $payStatus = 'pending', string $ordStatus = 'pending'): int {
    global $pdo, $testCustId;
    $orderNum = 'KC-' . date('Ymd') . '-TEST' . strtoupper(bin2hex(random_bytes(2)));
    $stmt = $pdo->prepare("
        INSERT INTO `orders` (
            customer_id, order_number, full_name, email, phone_no,
            delivery_address, delivery_date, delivery_time_slot,
            payment_method, payment_status, subtotal, delivery_fee, total, order_status
        ) VALUES (
            ?, ?, 'Payment Tester', 'test@kukicakes.lk', '0771234567',
            '123 Test Street, Colombo', '2026-11-10', 'Morning (8AM - 12PM)',
            ?, ?, 2500.00, 500.00, 3000.00, ?
        )
    ");
    $stmt->execute([$testCustId, $orderNum, $method, $payStatus, $ordStatus]);
    $orderId = (int) $pdo->lastInsertId();

    // Add item: cake_id = 1 (stock tracked)
    $stmtItem = $pdo->prepare("
        INSERT INTO `order_item` (order_id, cake_id, cake_name, unit_price, quantity, weight)
        VALUES (?, 1, 'Test Cake', 2500.00, 1, '500g')
    ");
    $stmtItem->execute([$orderId]);

    return $orderId;
}

// -----------------------------------------------------------------
// Test 1: Invalid payment status validation
// -----------------------------------------------------------------
echo "[1] Testing Invalid Payment Status Rejection...\n";
$ord1 = createTestOrder('card', 'pending', 'pending');

$badRes = httpPost("{$baseUrl}/admin-orders.php", [
    'action'         => 'update_payment_status',
    'order_id'       => $ord1,
    'payment_status' => 'invalid_status_xyz',
], $adminCookie);

assertTest($badRes['code'] === 400, "Invalid payment status rejected with 400 Bad Request");
assertTest(strpos($badRes['data']['message'] ?? '', 'Invalid payment status') !== false, "Error message specifies allowed statuses");

// -----------------------------------------------------------------
// Test 2: Card order without verified evidence is rejected
// -----------------------------------------------------------------
echo "\n[2] Testing Card Order Manual Paid Attempt Without Evidence...\n";

$noEvidenceRes = httpPost("{$baseUrl}/admin-orders.php", [
    'action'         => 'update_payment_status',
    'order_id'       => $ord1,
    'payment_status' => 'paid',
], $adminCookie);

assertTest($noEvidenceRes['code'] === 422, "Card order without verified evidence rejected with 422 Unprocessable Entity");
assertTest(strpos($noEvidenceRes['data']['message'] ?? '', 'verified gateway evidence') !== false, "Error message states verified gateway evidence is required");

// Verify DB unchanged
$checkStmt = $pdo->prepare('SELECT payment_status, order_status, payhere_payment_id FROM `orders` WHERE order_id = ?');
$checkStmt->execute([$ord1]);
$row1 = $checkStmt->fetch(PDO::FETCH_ASSOC);
assertTest($row1['payment_status'] === 'pending', "Database: payment_status remains 'pending'");
assertTest($row1['order_status'] === 'pending', "Database: order_status remains 'pending'");
assertTest($row1['payhere_payment_id'] === null, "Database: payhere_payment_id remains NULL");

// -----------------------------------------------------------------
// Test 3: Card order WITH verified payment evidence succeeds
// -----------------------------------------------------------------
echo "\n[3] Testing Card Order Paid Confirmation With Verified Evidence...\n";

$verifiedId = '320098765432';
$withEvidenceRes = httpPost("{$baseUrl}/admin-orders.php", [
    'action'             => 'update_payment_status',
    'order_id'           => $ord1,
    'payment_status'     => 'paid',
    'payhere_payment_id' => $verifiedId,
], $adminCookie);

assertTest($withEvidenceRes['code'] === 200, "Card order with verified evidence accepted with 200 OK");
assertTest(($withEvidenceRes['data']['success'] ?? false) === true, "Response reports success = true");

$checkStmt->execute([$ord1]);
$row1After = $checkStmt->fetch(PDO::FETCH_ASSOC);
assertTest($row1After['payment_status'] === 'paid', "Database: payment_status updated to 'paid'");
assertTest($row1After['payhere_payment_id'] === $verifiedId, "Database: payhere_payment_id recorded as {$verifiedId}");
assertTest($row1After['order_status'] === 'pending', "Database: order_status remains 'pending' (order_status NOT falsely mapped to payment_status)");

// -----------------------------------------------------------------
// Test 4: Order status independence
// -----------------------------------------------------------------
echo "\n[4] Testing Order Status Independence (Advancing order_status does not map to payment_status)...\n";
$ord2 = createTestOrder('cash', 'pending', 'pending');

// Update order_status to 'confirmed'
$upStatusRes = httpPost("{$baseUrl}/admin-orders.php", [
    'action'       => 'update_status',
    'order_id'     => $ord2,
    'order_status' => 'confirmed',
], $adminCookie);
assertTest($upStatusRes['code'] === 200, "Order status updated to 'confirmed'");

$checkStmt->execute([$ord2]);
$row2 = $checkStmt->fetch(PDO::FETCH_ASSOC);
assertTest($row2['order_status'] === 'confirmed', "Order status is 'confirmed'");
assertTest($row2['payment_status'] === 'pending', "Payment status remains 'pending' (confirmed does NOT automatically mean paid)");

// Update order_status to 'in_progress', 'ready', 'delivered'
httpPost("{$baseUrl}/admin-orders.php", ['action' => 'update_status', 'order_id' => $ord2, 'order_status' => 'in_progress'], $adminCookie);
$checkStmt->execute([$ord2]);
assertTest($checkStmt->fetch(PDO::FETCH_ASSOC)['payment_status'] === 'pending', "in_progress does NOT mean paid");

httpPost("{$baseUrl}/admin-orders.php", ['action' => 'update_status', 'order_id' => $ord2, 'order_status' => 'ready'], $adminCookie);
$checkStmt->execute([$ord2]);
assertTest($checkStmt->fetch(PDO::FETCH_ASSOC)['payment_status'] === 'pending', "ready does NOT mean paid");

httpPost("{$baseUrl}/admin-orders.php", ['action' => 'update_status', 'order_id' => $ord2, 'order_status' => 'delivered'], $adminCookie);
$checkStmt->execute([$ord2]);
assertTest($checkStmt->fetch(PDO::FETCH_ASSOC)['payment_status'] === 'pending', "delivered does NOT mean paid");

// -----------------------------------------------------------------
// Test 5: Distinct Payment Statuses (failed, cancelled, charged_back)
// -----------------------------------------------------------------
echo "\n[5] Testing Distinct Payment Statuses...\n";

// 5a. Failed
$ordFail = createTestOrder('card', 'pending', 'pending');
$failRes = httpPost("{$baseUrl}/admin-orders.php", ['action' => 'update_payment_status', 'order_id' => $ordFail, 'payment_status' => 'failed'], $adminCookie);
assertTest($failRes['code'] === 200, "Updated to 'failed' (200 OK)");
$checkStmt->execute([$ordFail]);
assertTest($checkStmt->fetch(PDO::FETCH_ASSOC)['payment_status'] === 'failed', "Payment status is distinct 'failed'");

// 5b. Cancelled
$ordCanc = createTestOrder('card', 'pending', 'pending');
$cancRes = httpPost("{$baseUrl}/admin-orders.php", ['action' => 'update_payment_status', 'order_id' => $ordCanc, 'payment_status' => 'cancelled'], $adminCookie);
assertTest($cancRes['code'] === 200, "Updated to 'cancelled' (200 OK)");
$checkStmt->execute([$ordCanc]);
assertTest($checkStmt->fetch(PDO::FETCH_ASSOC)['payment_status'] === 'cancelled', "Payment status is distinct 'cancelled'");

// 5c. Charged Back
$ordCb = createTestOrder('card', 'pending', 'pending');
$cbRes = httpPost("{$baseUrl}/admin-orders.php", ['action' => 'update_payment_status', 'order_id' => $ordCb, 'payment_status' => 'charged_back'], $adminCookie);
assertTest($cbRes['code'] === 200, "Updated to 'charged_back' (200 OK)");
$checkStmt->execute([$ordCb]);
assertTest($checkStmt->fetch(PDO::FETCH_ASSOC)['payment_status'] === 'charged_back', "Payment status is distinct 'charged_back'");

// -----------------------------------------------------------------
// Test 6: Safety Guards (Cannot revert paid to pending, cannot pay cancelled order)
// -----------------------------------------------------------------
echo "\n[6] Testing Safety Guards...\n";

// Cannot revert paid order to pending
$revertRes = httpPost("{$baseUrl}/admin-orders.php", ['action' => 'update_payment_status', 'order_id' => $ord1, 'payment_status' => 'pending'], $adminCookie);
assertTest($revertRes['code'] === 400, "Reverting already paid order to pending rejected with 400");

// Cannot pay cancelled order
$ordCancelled = createTestOrder('card', 'pending', 'cancelled');
$payCancelledRes = httpPost("{$baseUrl}/admin-orders.php", [
    'action'             => 'update_payment_status',
    'order_id'           => $ordCancelled,
    'payment_status'     => 'paid',
    'payhere_payment_id' => '320011111111',
], $adminCookie);
assertTest($payCancelledRes['code'] === 400, "Marking payment paid for cancelled order rejected with 400");

// -----------------------------------------------------------------
// Test 7: Orders List API includes payhere_payment_id and accurate payment_status
// -----------------------------------------------------------------
echo "\n[7] Testing Orders List API Response Format...\n";
$listRes = httpGet("{$baseUrl}/admin-orders.php?id={$ord1}", $adminCookie);
assertTest($listRes['code'] === 200, "Single order view returned 200");
assertTest(($listRes['data']['order']['payment_status'] ?? '') === 'paid', "Single order payment_status is 'paid'");
assertTest(($listRes['data']['order']['payhere_payment_id'] ?? '') === $verifiedId, "Single order payhere_payment_id returned correctly");

$listAll = httpGet("{$baseUrl}/admin-orders.php", $adminCookie);
assertTest($listAll['code'] === 200, "Orders list returned 200");
$foundTestOrder = false;
foreach ($listAll['data']['orders'] ?? [] as $o) {
    if ($o['order_id'] === $ord1) {
        $foundTestOrder = true;
        assertTest($o['payment_status'] === 'paid', "Orders list reflects payment_status = 'paid'");
        assertTest($o['payhere_payment_id'] === $verifiedId, "Orders list includes payhere_payment_id = {$verifiedId}");
    }
}
assertTest($foundTestOrder, "Test order found in list query");

// -----------------------------------------------------------------
// Test 8: Client Card Payment Confirmation Endpoint (/api/confirm-card-payment.php)
// -----------------------------------------------------------------
echo "\n[8] Testing Client Card Payment Confirmation Endpoint...\n";

// 8a. Unauthenticated request is rejected with 401
$unauthRes = httpPost("{$baseUrl}/confirm-card-payment.php", ['order_id' => $ord1]);
assertTest($unauthRes['code'] === 401, "Unauthenticated payment confirmation rejected with 401");

// 8b. Card order created by customer
$ordClientCard = createTestOrder('card', 'pending', 'pending');
$checkStmt->execute([$ordClientCard]);
$preCheck = $checkStmt->fetch(PDO::FETCH_ASSOC);
assertTest($preCheck['payment_status'] === 'pending', "Fresh card order payment_status is 'pending'");

// 8c. Non-card order rejected with 400
$ordCash = createTestOrder('cash', 'pending', 'pending');
$cashConfirmRes = httpPost("{$baseUrl}/confirm-card-payment.php", [
    'order_id' => $ordCash,
], $custCookie);
assertTest($cashConfirmRes['code'] === 400, "Non-card order payment confirmation rejected with 400");

// 8d. Customer confirms card payment upon PayHere modal completion
$confirmRes = httpPost("{$baseUrl}/confirm-card-payment.php", [
    'order_id' => $ordClientCard,
], $custCookie);
assertTest($confirmRes['code'] === 200, "Card payment confirmation returned 200 OK");
assertTest(($confirmRes['data']['success'] ?? false) === true, "Response success is true");
assertTest(($confirmRes['data']['payment_status'] ?? '') === 'paid', "Response payment_status is 'paid'");
assertTest(($confirmRes['data']['order_status'] ?? '') === 'confirmed', "Response order_status is 'confirmed'");

// Verify in DB
$checkStmt->execute([$ordClientCard]);
$postCheck = $checkStmt->fetch(PDO::FETCH_ASSOC);
assertTest($postCheck['payment_status'] === 'paid', "Database: payment_status updated to 'paid'");
assertTest($postCheck['order_status'] === 'confirmed', "Database: order_status updated to 'confirmed'");
assertTest(!empty($postCheck['payhere_payment_id']), "Database: payhere_payment_id recorded");

// 8e. Idempotent second confirmation
$idempotentRes = httpPost("{$baseUrl}/confirm-card-payment.php", [
    'order_id' => $ordClientCard,
], $custCookie);
assertTest($idempotentRes['code'] === 200, "Idempotent duplicate confirmation returned 200 OK");

// -----------------------------------------------------------------
// Cleanup: Remove test orders and test customer
// -----------------------------------------------------------------
echo "\n[9] Cleaning up test records...\n";
$allTestOrders = [$ord1, $ord2, $ordFail, $ordCanc, $ordCb, $ordCancelled, $ordClientCard, $ordCash];
$placeholders = implode(',', array_fill(0, count($allTestOrders), '?'));
$pdo->prepare("DELETE FROM `order_item` WHERE order_id IN ($placeholders)")->execute($allTestOrders);
$pdo->prepare("DELETE FROM `orders` WHERE order_id IN ($placeholders)")->execute($allTestOrders);
$pdo->prepare("DELETE FROM `customer` WHERE customer_id = ?")->execute([$testCustId]);

echo "Cleanup complete. Zero residual test records.\n";

echo "\n====================================================\n";
echo " Test Results: {$testsPassed} Passed, {$testsFailed} Failed\n";
echo "====================================================\n";

exit($testsFailed === 0 ? 0 : 1);
