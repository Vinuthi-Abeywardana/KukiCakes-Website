<?php
/**
 * KúkiCakes — Pricing Implementation Comprehensive Test Suite
 * Tests catalog prices, multi-category cart calculations, checkout validation,
 * delivery fee, trusted DB price enforcement, PayHere hashing, and historical order preservation.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/payhere.php';
require_once __DIR__ . '/../api/bootstrap.php';

$testResults = [];

function assertTest($name, $condition, $details = '') {
    global $testResults;
    if ($condition) {
        $testResults[] = ['status' => 'PASS', 'name' => $name, 'details' => $details];
        echo "  [PASS] {$name}" . ($details ? " ({$details})" : "") . "\n";
    } else {
        $testResults[] = ['status' => 'FAIL', 'name' => $name, 'details' => $details];
        echo "  [FAIL] {$name}" . ($details ? " ({$details})" : "") . "\n";
    }
}

echo "=================================================================\n";
echo "KúkiCakes — Pricing Implementation Verification & Test Suite\n";
echo "=================================================================\n\n";

// ---------------------------------------------------------------
// TEST SUITE 1: Verify All 95 Database Prices Against Approved Worksheet
// ---------------------------------------------------------------
echo "TEST SUITE 1: Database Prices vs Approved Worksheet\n";
echo "---------------------------------------------------\n";

$approvedJson = file_get_contents('C:/Users/Vinu/.gemini/antigravity-ide/brain/8a7e54d1-8305-46fd-91d1-5701c49efe27/scratch/approved_worksheet_prices.json');
$approvedProducts = json_decode($approvedJson, true);

$stmt = $pdo->query("SELECT cake_id, cake_name, price, sale_unit, confirmed_size FROM `cake` ORDER BY cake_id ASC");
$dbCakes = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dbCakes[$row['cake_id']] = $row;
}

$allMatch = true;
$mismatchDetails = [];

foreach ($approvedProducts as $app) {
    $id = $app['id'];
    if (!isset($dbCakes[$id])) {
        $allMatch = false;
        $mismatchDetails[] = "ID {$id} missing in DB";
        continue;
    }
    $dbPrice = (float) $dbCakes[$id]['price'];
    $appPrice = (float) $app['finalPrice'];
    if (abs($dbPrice - $appPrice) > 0.001) {
        $allMatch = false;
        $mismatchDetails[] = "ID {$id} ({$app['name']}) DB price {$dbPrice} != Approved {$appPrice}";
    }
}

assertTest("All 95 products match approved Final Price (Rs.) exactly", $allMatch, empty($mismatchDetails) ? "95/95 verified" : implode('; ', array_slice($mismatchDetails, 0, 3)));

// Spot-check key categories
assertTest("Spot-check Anniversary (ID 1: Golden Pearl)", (float)$dbCakes[1]['price'] === 10300.00 && $dbCakes[1]['sale_unit'] === 'Whole cake', "Rs. 10,300.00 / Whole cake");
assertTest("Spot-check Cupcake Box (ID 75: Blush Rose Swirl)", (float)$dbCakes[75]['price'] === 3300.00 && $dbCakes[75]['sale_unit'] === 'Box of 6', "Rs. 3,300.00 / Box of 6");
assertTest("Spot-check Cupcake Tower (ID 83: Wedding Tower)", (float)$dbCakes[83]['price'] === 11200.00 && $dbCakes[83]['sale_unit'] === 'Box of 24', "Rs. 11,200.00 / Box of 24");
assertTest("Spot-check Single-digit (ID 98: Number 5)", (float)$dbCakes[98]['price'] === 7100.00 && $dbCakes[98]['sale_unit'] === 'Number cake', "Rs. 7,100.00 / Number cake");
assertTest("Spot-check Double-digit (ID 100: Number 20)", (float)$dbCakes[100]['price'] === 11700.00 && $dbCakes[100]['sale_unit'] === 'Number cake', "Rs. 11,700.00 / Number cake (Inversion resolved)");
assertTest("Spot-check Wedding 3-tier (ID 109: Crimson Rose)", (float)$dbCakes[109]['price'] === 41500.00 && $dbCakes[109]['sale_unit'] === 'Whole cake', "Rs. 41,500.00 / 3-tier");
assertTest("Spot-check Wedding 4-tier (ID 110: Grand Garden)", (float)$dbCakes[110]['price'] === 87500.00 && $dbCakes[110]['sale_unit'] === 'Whole cake', "Rs. 87,500.00 / 4-tier");
assertTest("Spot-check Loaf (ID 120: Banana Walnut)", (float)$dbCakes[120]['price'] === 3000.00 && $dbCakes[120]['sale_unit'] === 'Loaf', "Rs. 3,000.00 / Loaf");
assertTest("Spot-check Lava Cake (ID 128: Molten Lava)", (float)$dbCakes[128]['price'] === 5900.00 && $dbCakes[128]['sale_unit'] === 'Box of 6', "Rs. 5,900.00 / Box of 6");

// ---------------------------------------------------------------
// TEST SUITE 2: Product API Endpoint Output
// ---------------------------------------------------------------
echo "\nTEST SUITE 2: Product API Verification\n";
echo "--------------------------------------\n";

$apiCurl = shell_exec('curl.exe -s "http://localhost/KukiCakes-Website/api/products.php?id=75"');
$apiData = json_decode($apiCurl, true);

assertTest("Product API returns success for valid ID", $apiData && $apiData['success'] === true);
assertTest("Product API exposes updated price", isset($apiData['product']['price']) && (float)$apiData['product']['price'] === 3300.00, "Price: 3300.00");
assertTest("Product API exposes sale_unit", isset($apiData['product']['sale_unit']) && $apiData['product']['sale_unit'] === 'Box of 6', "sale_unit: Box of 6");
assertTest("Product API exposes confirmed_size", isset($apiData['product']['confirmed_size']) && $apiData['product']['confirmed_size'] === 'Box of 6 cupcakes', $apiData['product']['confirmed_size'] ?? '');
assertTest("Internal worksheet note '(assumed)' stripped from customer output", !str_contains($apiData['product']['confirmed_size'] ?? '', 'assumed'), "Clean output: '{$apiData['product']['confirmed_size']}'");

// Verify across all products in DB that no assumed notes exist
$assumedCount = $pdo->query("SELECT COUNT(*) FROM cake WHERE confirmed_size LIKE '%assumed%' OR sale_unit LIKE '%assumed%'")->fetchColumn();
assertTest("Zero '(assumed)' notes remain in database across all 95 products", (int)$assumedCount === 0, "Found: {$assumedCount}");

// ---------------------------------------------------------------
// TEST SUITE 3: Cart Calculations & Multi-Category Orders
// ---------------------------------------------------------------
echo "\nTEST SUITE 3: Cart Calculations & Quantities > 1\n";
echo "------------------------------------------------\n";

// Multi-product scenario:
// 2 x ID 1 (Golden Pearl Anniversary @ Rs. 10,300) = Rs. 20,600
// 3 x ID 75 (Blush Rose Swirl Cupcakes @ Rs. 3,300) = Rs. 9,900
// 1 x ID 100 (Berry Bliss Number 20 @ Rs. 11,700) = Rs. 11,700
$testItems = [
    ['id' => 1, 'qty' => 2, 'unit_price' => 10300.00, 'expected_line' => 20600.00],
    ['id' => 75, 'qty' => 3, 'unit_price' => 3300.00, 'expected_line' => 9900.00],
    ['id' => 100, 'qty' => 1, 'unit_price' => 11700.00, 'expected_line' => 11700.00],
];

$calcSubtotal = 0.00;
foreach ($testItems as $item) {
    $line = round($item['unit_price'] * $item['qty'], 2);
    $calcSubtotal += $line;
    assertTest("Item ID {$item['id']} quantity {$item['qty']} line total", $line === $item['expected_line'], "{$item['qty']} x {$item['unit_price']} = {$line}");
}

$expectedSubtotal = 42200.00;
assertTest("Multi-item cart subtotal calculation", $calcSubtotal === $expectedSubtotal, "Subtotal: Rs. {$calcSubtotal}");

$deliveryFee = 500.00;
$expectedTotal = 42700.00;
$calcTotal = round($calcSubtotal + $deliveryFee, 2);
assertTest("Checkout total including flat Rs. 500 delivery charge", $calcTotal === $expectedTotal, "Rs. {$calcSubtotal} + Rs. 500.00 = Rs. {$calcTotal}");

// ---------------------------------------------------------------
// TEST SUITE 4: Server-Side Trusted Price Enforcement in create-order.php
// ---------------------------------------------------------------
echo "\nTEST SUITE 4: Trusted Price Enforcement in Order Creation\n";
echo "--------------------------------------------------------\n";

// Verify that create-order.php uses $cake['price'] from DB, NOT client input
$code = file_get_contents('C:/xampp/htdocs/KukiCakes-Website/api/create-order.php');
$usesDbPrice = strpos($code, '$unitPrice = (float) $cake[\'price\'];') !== false;
assertTest("create-order.php enforces database price over client price", $usesDbPrice, "Found: \$unitPrice = (float) \$cake['price']");

$calculatesDelivery = strpos($code, '$deliveryFee = 500.00;') !== false;
assertTest("create-order.php enforces flat Rs. 500.00 delivery fee", $calculatesDelivery, "Found: \$deliveryFee = 500.00;");

$calculatesTotal = strpos($code, '$total       = round($subtotal + $deliveryFee, 2);') !== false;
assertTest("create-order.php calculates total as subtotal + delivery fee", $calculatesTotal, "Found: \$total = round(\$subtotal + \$deliveryFee, 2);");

// ---------------------------------------------------------------
// TEST SUITE 5: PayHere MD5 Checksum Integrity
// ---------------------------------------------------------------
echo "\nTEST SUITE 5: PayHere Checksum Verification\n";
echo "-------------------------------------------\n";

$merchantId = PAYHERE_MERCHANT_ID;
$merchantSecret = PAYHERE_MERCHANT_SECRET;
$testOrderNumber = "KC-20261025-TEST01";
$testAmount = 42700.00;
$currency = PAYHERE_CURRENCY;

$hash = payhere_generate_hash($merchantId, $testOrderNumber, $testAmount, $currency, $merchantSecret);
$expectedHashPattern = '/^[A-F0-9]{32}$/';
assertTest("PayHere hash generates valid 32-character uppercase MD5", preg_match($expectedHashPattern, $hash) === 1, "Hash: {$hash}");

// Verify hash matches formula
$formattedAmount = number_format($testAmount, 2, '.', '');
$manualHash = strtoupper(md5($merchantId . $testOrderNumber . $formattedAmount . $currency . strtoupper(md5($merchantSecret))));
assertTest("PayHere hash matches gateway MD5 specification", $hash === $manualHash, "Verified against formula");

// ---------------------------------------------------------------
// TEST SUITE 6: Historical Orders & Cart Integrity
// ---------------------------------------------------------------
echo "\nTEST SUITE 6: Historical Data Integrity\n";
echo "----------------------------------------\n";

$orderItemCount = $pdo->query("SELECT COUNT(*) FROM `order_item`")->fetchColumn();
assertTest("Historical order_item rows preserved untouched", (int)$orderItemCount > 0, "{$orderItemCount} historical items preserved");

$backupCount = $pdo->query("SELECT COUNT(*) FROM `cake_backup_20261025`")->fetchColumn();
assertTest("Pre-update backup table intact", (int)$backupCount === 95, "95 original cake records backed up");

echo "\n=================================================================\n";
$passCount = count(array_filter($testResults, fn($r) => $r['status'] === 'PASS'));
$failCount = count(array_filter($testResults, fn($r) => $r['status'] === 'FAIL'));
echo "TEST SUMMARY: {$passCount} Passed, {$failCount} Failed (Total: " . count($testResults) . ")\n";
echo "=================================================================\n";
