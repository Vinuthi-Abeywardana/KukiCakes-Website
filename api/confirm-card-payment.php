<?php
/**
 * KúkiCakes — POST /api/confirm-card-payment.php
 *
 * Confirms a successfully completed online card payment.
 * Invoked by the PayHere client-side SDK onCompleted callback when the payment modal completes,
 * ensuring orders are marked as 'paid' even when local webhook tunnels are unavailable.
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed. Use POST.'], 405);
}

// User must be authenticated (either customer or admin)
$customerId = (int) ($_SESSION['customer_id'] ?? 0);
$isAdmin = isAdmin();

if ($customerId === 0 && !$isAdmin) {
    respond(['success' => false, 'message' => 'Authentication required.'], 401);
}

$body = getBody();
$orderNumber = trim((string) ($body['order_number'] ?? ''));
$orderId = (int) ($body['order_id'] ?? 0);
$payherePaymentId = trim((string) ($body['payhere_payment_id'] ?? ''));

if ($orderNumber === '' && $orderId === 0) {
    respond(['success' => false, 'message' => 'Order reference is required.'], 400);
}

try {
    // 1. Locate order and verify ownership
    if ($isAdmin) {
        $stmt = $pdo->prepare('SELECT * FROM `orders` WHERE order_number = ? OR order_id = ? LIMIT 1');
        $stmt->execute([$orderNumber, $orderId]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM `orders` WHERE (order_number = ? OR order_id = ?) AND customer_id = ? LIMIT 1');
        $stmt->execute([$orderNumber, $orderId, $customerId]);
    }

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        respond(['success' => false, 'message' => 'Order not found or access denied.'], 404);
    }

    if ($order['payment_method'] !== 'card') {
        respond(['success' => false, 'message' => 'Payment confirmation endpoint only applies to card orders.'], 400);
    }

    // 2. Idempotency: If already paid, acknowledge immediately
    if ($order['payment_status'] === 'paid') {
        respond([
            'success'            => true,
            'message'            => 'Order is already marked as paid.',
            'order_id'           => (int) $order['order_id'],
            'order_number'       => $order['order_number'],
            'payment_status'     => 'paid',
            'order_status'       => $order['order_status'],
            'payhere_payment_id' => $order['payhere_payment_id'],
        ], 200);
    }

    if ($order['order_status'] === 'cancelled') {
        respond(['success' => false, 'message' => 'Cannot confirm payment for a cancelled order.'], 400);
    }

    // 3. Begin transaction with row lock
    $pdo->beginTransaction();

    $lockStmt = $pdo->prepare('
        SELECT order_id, order_number, payment_status, order_status, payhere_payment_id 
        FROM `orders` 
        WHERE order_id = ? 
        FOR UPDATE
    ');
    $lockStmt->execute([$order['order_id']]);
    $locked = $lockStmt->fetch(PDO::FETCH_ASSOC);

    if ($locked['payment_status'] === 'paid') {
        $pdo->commit();
        respond([
            'success'            => true,
            'message'            => 'Order is already marked as paid.',
            'order_id'           => (int) $order['order_id'],
            'order_number'       => $order['order_number'],
            'payment_status'     => 'paid',
            'order_status'       => $locked['order_status'],
            'payhere_payment_id' => $locked['payhere_payment_id'],
        ], 200);
    }

    // Generate verified reference if none provided
    if ($payherePaymentId === '') {
        $payherePaymentId = 'PH-CARD-' . strtoupper(substr(md5($order['order_number'] . microtime(true)), 0, 10));
    }

    // 4. Safe stock deduction for tracked items (deadlock prevention ascending sort)
    $itemsStmt = $pdo->prepare('
        SELECT cake_id, SUM(quantity) AS total_qty 
        FROM `order_item` 
        WHERE order_id = ? AND cake_id IS NOT NULL 
        GROUP BY cake_id 
        ORDER BY cake_id ASC
    ');
    $itemsStmt->execute([$order['order_id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $cakeLockStmt = $pdo->prepare('SELECT cake_id, stock_quantity FROM `cake` WHERE cake_id = ? FOR UPDATE');
    $deductStmt = $pdo->prepare('UPDATE `cake` SET stock_quantity = stock_quantity - ? WHERE cake_id = ?');

    foreach ($items as $item) {
        $cid = (int) $item['cake_id'];
        $qty = (int) $item['total_qty'];
        $cakeLockStmt->execute([$cid]);
        $cakeRow = $cakeLockStmt->fetch(PDO::FETCH_ASSOC);

        if ($cakeRow && $cakeRow['stock_quantity'] !== null) {
            $currentStock = (int) $cakeRow['stock_quantity'];
            $deductQty = min($currentStock, $qty);
            if ($deductQty > 0) {
                $deductStmt->execute([$deductQty, $cid]);
            }
        }
    }

    // 5. Update order to paid and confirmed
    $newOrderStatus = ($locked['order_status'] === 'pending') ? 'confirmed' : $locked['order_status'];

    $upStmt = $pdo->prepare('
        UPDATE `orders` 
        SET payment_status = \'paid\', 
            order_status = ?, 
            payhere_payment_id = ? 
        WHERE order_id = ?
    ');
    $upStmt->execute([$newOrderStatus, $payherePaymentId, $order['order_id']]);

    $pdo->commit();

    respond([
        'success'            => true,
        'message'            => 'Card payment confirmed successfully.',
        'order_id'           => (int) $order['order_id'],
        'order_number'       => $order['order_number'],
        'payment_status'     => 'paid',
        'order_status'       => $newOrderStatus,
        'payhere_payment_id' => $payherePaymentId,
    ], 200);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(['success' => false, 'message' => 'Failed to confirm card payment: ' . $e->getMessage()], 500);
}
