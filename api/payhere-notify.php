<?php
/**
 * KúkiCakes — POST /api/payhere-notify.php
 *
 * PayHere Server-to-Server Payment Notification (IPN) Webhook.
 *
 * Requirements & Security:
 * 1. Receives asynchronous POST notifications directly from PayHere payment gateway.
 * 2. Validates merchant_id against server configuration.
 * 3. Verifies cryptographic MD5 signature using server-only PAYHERE_MERCHANT_SECRET.
 * 4. Verifies order exists, currency is LKR, and amount exactly matches order total.
 * 5. Updates `payment_status` ('paid', 'failed', 'cancelled', 'charged_back', 'pending').
 * 6. Sets `order_status` to 'confirmed' upon verified successful payment (status_code 2).
 * 7. Stores PayHere payment_id reference for auditing.
 * 8. Implements idempotency: duplicate webhook calls return 200 OK without re-processing.
 * 9. Never trusts client-side callbacks as proof of payment.
 */

// Application timezone
date_default_timezone_set('Asia/Colombo');

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Load database connection & PayHere configuration
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/payhere.php';

// Helper response function
function ipnRespond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Only POST requests are permitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ipnRespond(['success' => false, 'message' => 'Method not allowed. Use POST.'], 405);
}

// Parse incoming payload (supports urlencoded form data or raw body)
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = $json;
        } else {
            parse_str($raw, $parsed);
            if (!empty($parsed)) {
                $input = $parsed;
            }
        }
    }
}

// Extract PayHere notification parameters
$merchantId      = trim((string) ($input['merchant_id'] ?? ''));
$orderId         = trim((string) ($input['order_id'] ?? ''));
$paymentId       = trim((string) ($input['payment_id'] ?? ''));
$payhereAmount   = trim((string) ($input['payhere_amount'] ?? ''));
$payhereCurrency = trim((string) ($input['payhere_currency'] ?? ''));
$statusCode      = trim((string) ($input['status_code'] ?? ''));
$md5sig          = trim((string) ($input['md5sig'] ?? ''));

// 1. Validate required fields presence
if ($merchantId === '' || $orderId === '' || $payhereAmount === '' || $payhereCurrency === '' || $statusCode === '' || $md5sig === '') {
    error_log('[PayHere IPN] Missing required notification fields: ' . json_encode($input));
    ipnRespond(['success' => false, 'message' => 'Missing required notification fields.'], 400);
}

// 2. Validate merchant_id
if ($merchantId !== PAYHERE_MERCHANT_ID) {
    error_log("[PayHere IPN] Merchant ID mismatch. Expected: " . PAYHERE_MERCHANT_ID . ", Received: {$merchantId}");
    ipnRespond(['success' => false, 'message' => 'Invalid merchant ID.'], 400);
}

// 3. Cryptographically verify MD5 signature
if (!payhere_verify_signature(PAYHERE_MERCHANT_ID, $orderId, $payhereAmount, $payhereCurrency, $statusCode, $md5sig, PAYHERE_MERCHANT_SECRET)) {
    error_log("[PayHere IPN] Signature verification failed for order: {$orderId}");
    ipnRespond(['success' => false, 'message' => 'Signature verification failed.'], 400);
}

// 4. Identify the order in the database (by unique order_number or primary order_id)
try {
    $stmt = $pdo->prepare('
        SELECT order_id, order_number, customer_id, total, payment_status, order_status, payhere_payment_id, special_instructions
        FROM `orders`
        WHERE order_number = :order_num OR (order_id = :order_id AND :is_num REGEXP "^[0-9]+$")
        LIMIT 1
    ');
    $stmt->execute([
        ':order_num' => $orderId,
        ':order_id'  => $orderId,
        ':is_num'    => $orderId,
    ]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[PayHere IPN] Database query error: ' . $e->getMessage());
    ipnRespond(['success' => false, 'message' => 'Database error.'], 500);
}

if (!$order) {
    error_log("[PayHere IPN] Order not found for order reference: {$orderId}");
    ipnRespond(['success' => false, 'message' => 'Order not found.'], 404);
}

// 5. Verify currency and amount match order record
if (strtoupper($payhereCurrency) !== strtoupper(PAYHERE_CURRENCY)) {
    error_log("[PayHere IPN] Currency mismatch: received {$payhereCurrency}, expected " . PAYHERE_CURRENCY);
    ipnRespond(['success' => false, 'message' => 'Currency mismatch.'], 400);
}

$orderTotal = (float) $order['total'];
$receivedAmount = (float) $payhereAmount;
if (abs($orderTotal - $receivedAmount) > 0.01) {
    error_log("[PayHere IPN] Amount mismatch: Order total {$orderTotal}, Received {$receivedAmount}");
    ipnRespond(['success' => false, 'message' => 'Payment amount mismatch.'], 400);
}

// 6. Idempotency: If already marked as paid with status_code 2, acknowledge immediately
if ($order['payment_status'] === 'paid' && $statusCode === '2') {
    ipnRespond([
        'success'        => true,
        'message'        => 'Notification already processed. Order is already marked as paid.',
        'order_number'   => $order['order_number'],
        'payment_status' => 'paid',
    ], 200);
}

// 7. Determine updated payment_status and order_status based on PayHere status_code:
//    2  = Success
//    0  = Pending (e.g. offline bank approval)
//   -1  = Canceled
//   -2  = Failed
//   -3  = Chargedback
$newPaymentStatus = $order['payment_status'];
$newOrderStatus   = $order['order_status'];

switch ($statusCode) {
    case '2': // Payment Successful
        $newPaymentStatus = 'paid';
        // Only advance order_status to 'confirmed' if it was 'pending'
        if ($order['order_status'] === 'pending') {
            $newOrderStatus = 'confirmed';
        }
        break;

    case '0': // Payment Pending
        $newPaymentStatus = 'pending';
        break;

    case '-1': // Payment Canceled
        $newPaymentStatus = 'cancelled';
        break;

    case '-2': // Payment Failed
        $newPaymentStatus = 'failed';
        break;

    case '-3': // Charged back
        $newPaymentStatus = 'charged_back';
        break;

    default:
        $newPaymentStatus = 'failed';
        break;
}

// 8. Update order and allocate stock in a database transaction with pessimistic locking
try {
    $pdo->beginTransaction();

    // 8a. Pessimistic row-level lock on the order to prevent concurrent webhook race conditions
    $lockStmt = $pdo->prepare('
        SELECT payment_status, order_status, payhere_payment_id, special_instructions 
        FROM `orders` 
        WHERE order_id = :order_id 
        FOR UPDATE
    ');
    $lockStmt->execute([':order_id' => $order['order_id']]);
    $lockedOrder = $lockStmt->fetch(PDO::FETCH_ASSOC);

    // Concurrency guard: If a concurrent notification already completed and marked it paid
    if ($lockedOrder && $lockedOrder['payment_status'] === 'paid' && $statusCode === '2') {
        $pdo->commit();
        ipnRespond([
            'success'        => true,
            'message'        => 'Notification already processed. Order is already marked as paid.',
            'order_number'   => $order['order_number'],
            'payment_status' => 'paid',
        ], 200);
    }

    $finalPaymentStatus = $newPaymentStatus;
    $finalOrderStatus   = $newOrderStatus;
    $finalInstructions  = $lockedOrder['special_instructions'] ?? $order['special_instructions'] ?? null;
    $shortageOccurred   = false;

    // Deduct stock ONLY on verified successful payment (statusCode === '2')
    // and only if the order was not already marked as paid before this transaction
    if ($statusCode === '2' && (!$lockedOrder || $lockedOrder['payment_status'] !== 'paid')) {
        // Query distinct order items aggregated by cake_id in strictly ascending order (deadlock prevention)
        $itemsStmt = $pdo->prepare('
            SELECT cake_id, SUM(quantity) AS total_qty 
            FROM `order_item` 
            WHERE order_id = :order_id AND cake_id IS NOT NULL
            GROUP BY cake_id
            ORDER BY cake_id ASC
        ');
        $itemsStmt->execute([':order_id' => $order['order_id']]);
        $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Lock all distinct cake rows in strictly ascending order and verify stock availability
        $cakeLockStmt = $pdo->prepare('
            SELECT cake_id, cake_name, stock_quantity 
            FROM `cake` 
            WHERE cake_id = :cake_id 
            FOR UPDATE
        ');

        $allTrackedSufficient = true;
        $shortages = [];
        $cakesToDeduct = [];

        foreach ($orderItems as $item) {
            $cid = (int) $item['cake_id'];
            $reqQty = (int) $item['total_qty'];

            $cakeLockStmt->execute([':cake_id' => $cid]);
            $cakeRow = $cakeLockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$cakeRow) {
                $allTrackedSufficient = false;
                $shortages[] = "Product #{$cid} not found in catalog";
                break;
            }

            // Untracked items (stock_quantity IS NULL) are ignored for inventory counts
            if ($cakeRow['stock_quantity'] !== null) {
                $currentStock = (int) $cakeRow['stock_quantity'];
                if ($currentStock < $reqQty) {
                    $allTrackedSufficient = false;
                    $shortages[] = "'{$cakeRow['cake_name']}' (available: {$currentStock}, requested: {$reqQty})";
                } else {
                    $cakesToDeduct[] = [
                        'cake_id'  => $cid,
                        'quantity' => $reqQty,
                    ];
                }
            }
        }

        if ($allTrackedSufficient) {
            // All items have sufficient stock: deduct conditionally for each item
            $stockStmt = $pdo->prepare('
                UPDATE `cake` 
                SET stock_quantity = stock_quantity - :qty
                WHERE cake_id = :cake_id AND stock_quantity IS NOT NULL AND stock_quantity >= :min_qty
            ');
            foreach ($cakesToDeduct as $deductItem) {
                $stockStmt->execute([
                    ':qty'     => (int) $deductItem['quantity'],
                    ':min_qty' => (int) $deductItem['quantity'],
                    ':cake_id' => (int) $deductItem['cake_id'],
                ]);
            }
            $finalOrderStatus = ($order['order_status'] === 'pending' ? 'confirmed' : $order['order_status']);
        } else {
            // INVENTORY SHORTAGE: Do NOT deduct partial stock. Record payment, but hold order out of normal fulfillment
            $shortageOccurred = true;
            $finalOrderStatus = 'pending'; // Remain pending instead of confirmed for administrative resolution
            
            $shortageAlert = '[INVENTORY SHORTAGE ALERT: Paid LKR ' . number_format($receivedAmount, 2, '.', '') . 
                ' via PayHere Ref #' . ($paymentId !== '' ? $paymentId : 'N/A') . ' on ' . date('Y-m-d H:i:s') . 
                ' but stock was depleted: ' . implode(', ', $shortages) . 
                '. Stock was NOT deducted; order held in pending status for administrative resolution.]';
            $existingNotes = trim((string) ($finalInstructions ?? ''));
            $finalInstructions = $existingNotes !== '' ? $shortageAlert . "\n" . $existingNotes : $shortageAlert;
            
            error_log("[PayHere IPN] Stock shortage for order {$order['order_number']}: " . implode('; ', $shortages));
        }
    }

    $updateStmt = $pdo->prepare('
        UPDATE `orders`
        SET payment_status       = :payment_status,
            order_status         = :order_status,
            payhere_payment_id   = :payhere_payment_id,
            special_instructions = :special_instructions
        WHERE order_id = :order_id
    ');
    $updateStmt->execute([
        ':payment_status'       => $finalPaymentStatus,
        ':order_status'         => $finalOrderStatus,
        ':payhere_payment_id'   => $paymentId !== '' ? $paymentId : ($lockedOrder['payhere_payment_id'] ?? $order['payhere_payment_id']),
        ':special_instructions' => $finalInstructions,
        ':order_id'             => $order['order_id'],
    ]);

    $pdo->commit();

    ipnRespond([
        'success'            => true,
        'message'            => $shortageOccurred 
            ? 'Payment recorded. Order flagged for administrative resolution due to inventory shortage.' 
            : 'Payment notification processed successfully.',
        'order_number'       => $order['order_number'],
        'payment_status'     => $finalPaymentStatus,
        'order_status'       => $finalOrderStatus,
        'payhere_payment_id' => $paymentId !== '' ? $paymentId : ($lockedOrder['payhere_payment_id'] ?? $order['payhere_payment_id']),
        'shortage'           => $shortageOccurred,
    ], 200);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[PayHere IPN] Database update error: ' . $e->getMessage());
    ipnRespond(['success' => false, 'message' => 'Failed to update order status.'], 500);
}

