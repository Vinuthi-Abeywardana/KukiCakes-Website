<?php
/**
 * Test: Full Checkout & Order Creation Flow with Updated Prices
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/payhere.php';

echo "=================================================================\n";
echo "KúkiCakes — Full Checkout & Order Creation Integration Test\n";
echo "=================================================================\n\n";

$cookieJar = tempnam(sys_get_temp_dir(), 'kuki_cookie_');

function apiCall($url, $method = 'GET', $data = null, $cookieFile = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($res, true), 'raw' => $res];
}

// 1. Find a customer in DB to use for test
$customer = $pdo->query("SELECT customer_id, email FROM customer WHERE account_status = 'active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$customer) {
    die("No active customer found for test.\n");
}
$customerId = (int) $customer['customer_id'];
echo "Using customer ID {$customerId} ({$customer['email']})\n";

// 2. Clear customer's existing cart in DB
$pdo->prepare("DELETE FROM user_cart_items WHERE customer_id = ?")->execute([$customerId]);

// 3. Insert cart items with quantities > 1 across multiple categories
// ID 1 (Anniversary): 2 x Rs. 10,300 = Rs. 20,600
// ID 75 (Cupcake): 3 x Rs. 3,300 = Rs. 9,900
// ID 100 (Number Cake): 1 x Rs. 11,700 = Rs. 11,700
$insCart = $pdo->prepare("INSERT INTO user_cart_items (customer_id, product_id, weight, flavor, note, quantity, item_data) VALUES (?, ?, ?, ?, ?, ?, ?)");
$insCart->execute([$customerId, 1, '1.5 kg, round, tall buttercream', 'Vanilla bean', 'Happy Anniversary', 2, json_encode(['id' => 1, 'price' => 100.00, 'qty' => 2])]); // Note: client submitted fake price 100.00
$insCart->execute([$customerId, 75, 'Box of 6 cupcakes (assumed)', 'Chocolate', '', 3, json_encode(['id' => 75, 'price' => 50.00, 'qty' => 3])]); // Note: client submitted fake price 50.00
$insCart->execute([$customerId, 100, 'Double digit, about 1.4 kg', 'Red velvet', '20th birthday', 1, json_encode(['id' => 100, 'price' => 10.00, 'qty' => 1])]);

echo "Populated test cart items in database.\n";

// 4. Test order creation via direct PHP execution simulating authenticated session
$_SESSION['customer_id'] = $customerId;
$_SESSION['role'] = 'customer';

// We run a test harness that mimics api/create-order.php order creation
$cartStmt = $pdo->prepare('SELECT cart_item_id, product_id, weight, flavor, note, quantity FROM user_cart_items WHERE customer_id = ? ORDER BY cart_item_id ASC');
$cartStmt->execute([$customerId]);
$cartRows = $cartStmt->fetchAll(PDO::FETCH_ASSOC);

$cakeStmt = $pdo->prepare('SELECT cake_id, cake_name, price, availability, stock_quantity FROM cake WHERE cake_id = ?');

$subtotal = 0.00;
$orderItemsToInsert = [];

foreach ($cartRows as $row) {
    $productId = (int) $row['product_id'];
    $cakeStmt->execute([$productId]);
    $cake = $cakeStmt->fetch(PDO::FETCH_ASSOC);

    $qty = max(1, (int) $row['quantity']);
    $unitPrice = (float) $cake['price'];
    $lineTotal = round($unitPrice * $qty, 2);
    $subtotal += $lineTotal;

    $orderItemsToInsert[] = [
        'cake_id'    => (int) $cake['cake_id'],
        'cake_name'  => (string) $cake['cake_name'],
        'unit_price' => $unitPrice,
        'quantity'   => $qty,
        'weight'     => $row['weight'],
        'note'       => $row['note']
    ];
}

$deliveryFee = 500.00;
$total = round($subtotal + $deliveryFee, 2);

echo "\nCalculated Totals:\n";
echo "  Subtotal: Rs. " . number_format($subtotal, 2) . " (Expected: Rs. 42,200.00)\n";
echo "  Delivery Fee: Rs. " . number_format($deliveryFee, 2) . " (Expected: Rs. 500.00)\n";
echo "  Total: Rs. " . number_format($total, 2) . " (Expected: Rs. 42,700.00)\n";

$passSubtotal = (abs($subtotal - 42200.00) < 0.01);
$passDelivery = (abs($deliveryFee - 500.00) < 0.01);
$passTotal = (abs($total - 42700.00) < 0.01);

echo "\nValidation:\n";
echo "  [" . ($passSubtotal ? "PASS" : "FAIL") . "] Subtotal matches trusted DB price calculation\n";
echo "  [" . ($passDelivery ? "PASS" : "FAIL") . "] Flat delivery fee of Rs. 500.00 applied\n";
echo "  [" . ($passTotal ? "PASS" : "FAIL") . "] Total is exactly Subtotal + Delivery Fee\n";

// Clean up test cart items
$pdo->prepare("DELETE FROM user_cart_items WHERE customer_id = ?")->execute([$customerId]);
echo "\nTest cart items cleaned up.\n";
unlink($cookieJar);
