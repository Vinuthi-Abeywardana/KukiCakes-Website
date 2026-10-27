<?php
/**
 * KúkiCakes — GET & POST /api/admin-orders.php
 *
 * Administrator Order Management API.
 *
 * Requirements:
 * 1. Enforces requireAdmin() — rejects guests (401) and customers (403).
 * 2. GET:
 *    - Lists orders with customer and item details, counts, and search/filter.
 *    - Single order view (?id=N or ?order_number=...) with full item lines and customer details.
 * 3. POST / PUT:
 *    - action: 'update_status' — updates order_status with strict validation:
 *      ['pending', 'confirmed', 'in_progress', 'ready', 'delivered', 'cancelled']
 *    - action: 'update_payment_status' — updates payment_status:
 *      ['pending', 'paid', 'failed', 'cancelled', 'charged_back']
 */

require_once __DIR__ . '/bootstrap.php';

// Enforce strict administrator authorization
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$validOrderStatuses   = ['pending', 'confirmed', 'in_progress', 'ready', 'delivered', 'cancelled'];
$validPaymentStatuses = ['pending', 'paid', 'failed', 'cancelled', 'charged_back'];

// ─────────────────────────────────────────────────────────────────
// GET: List Orders or View Single Order
// ─────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $orderIdParam = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : null;
    $orderNumParam = isset($_GET['order_number']) ? trim((string) $_GET['order_number']) : '';

    // ── Single Order Detail View ──────────────────────────────────
    if ($orderIdParam !== null || $orderNumParam !== '') {
        try {
            $stmt = $pdo->prepare('
                SELECT 
                    o.order_id,
                    o.customer_id,
                    o.order_number,
                    o.full_name,
                    o.email,
                    o.phone_no,
                    o.delivery_address,
                    o.delivery_date,
                    o.delivery_time_slot,
                    o.special_instructions,
                    o.payment_method,
                    o.payment_status,
                    o.payhere_payment_id,
                    o.subtotal,
                    o.delivery_fee,
                    o.total,
                    o.order_status,
                    o.created_at,
                    c.full_name AS registered_customer_name,
                    c.account_status AS customer_account_status
                FROM `orders` o
                LEFT JOIN `customer` c ON o.customer_id = c.customer_id
                WHERE o.order_id = :oid OR o.order_number = :onum
                LIMIT 1
            ');
            $stmt->execute([
                ':oid'  => $orderIdParam ?: 0,
                ':onum' => $orderNumParam ?: '',
            ]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                respond(['success' => false, 'message' => 'Order not found.'], 404);
            }

            // Fetch line items
            $itemsStmt = $pdo->prepare('
                SELECT 
                    oi.order_item_id,
                    oi.cake_id,
                    oi.flavor_id,
                    oi.cake_name,
                    oi.unit_price,
                    oi.quantity,
                    oi.weight,
                    oi.special_note,
                    (oi.unit_price * oi.quantity) AS line_total,
                    ck.image AS cake_image,
                    ck.stock_quantity AS current_cake_stock
                FROM `order_item` oi
                LEFT JOIN `cake` ck ON oi.cake_id = ck.cake_id
                WHERE oi.order_id = ?
                ORDER BY oi.order_item_id ASC
            ');
            $itemsStmt->execute([$order['order_id']]);
            $rawItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            $formattedItems = array_map(function ($it) {
                return [
                    'order_item_id'      => (int) $it['order_item_id'],
                    'cake_id'            => $it['cake_id'] !== null ? (int) $it['cake_id'] : null,
                    'flavor_id'          => $it['flavor_id'] !== null ? (int) $it['flavor_id'] : null,
                    'cake_name'          => (string) $it['cake_name'],
                    'unit_price'         => (float) $it['unit_price'],
                    'quantity'           => (int) $it['quantity'],
                    'weight'             => (string) ($it['weight'] ?: '500g'),
                    'special_note'       => (string) ($it['special_note'] ?? ''),
                    'line_total'         => (float) $it['line_total'],
                    'cake_image'         => (string) ($it['cake_image'] ?? ''),
                    'current_cake_stock' => $it['current_cake_stock'] !== null ? (int) $it['current_cake_stock'] : null,
                ];
            }, $rawItems);

            respond([
                'success' => true,
                'order'   => [
                    'order_id'             => (int) $order['order_id'],
                    'customer_id'          => $order['customer_id'] !== null ? (int) $order['customer_id'] : null,
                    'order_number'         => (string) $order['order_number'],
                    'full_name'            => (string) $order['full_name'],
                    'email'                => (string) $order['email'],
                    'phone_no'             => (string) $order['phone_no'],
                    'delivery_address'     => (string) $order['delivery_address'],
                    'delivery_date'        => (string) $order['delivery_date'],
                    'delivery_time_slot'   => (string) $order['delivery_time_slot'],
                    'special_instructions' => (string) ($order['special_instructions'] ?? ''),
                    'payment_method'       => (string) $order['payment_method'],
                    'payment_status'       => (string) $order['payment_status'],
                    'payhere_payment_id'   => $order['payhere_payment_id'] ? (string) $order['payhere_payment_id'] : null,
                    'subtotal'             => (float) $order['subtotal'],
                    'delivery_fee'         => (float) $order['delivery_fee'],
                    'total'                => (float) $order['total'],
                    'order_status'         => (string) $order['order_status'],
                    'created_at'           => (string) $order['created_at'],
                    'customer'             => [
                        'customer_id'    => $order['customer_id'] !== null ? (int) $order['customer_id'] : null,
                        'name'           => (string) ($order['registered_customer_name'] ?? $order['full_name']),
                        'email'          => (string) $order['email'],
                        'account_status' => (string) ($order['customer_account_status'] ?? 'guest'),
                    ],
                    'items'                => $formattedItems,
                    'item_count'           => count($formattedItems),
                ],
                'valid_order_statuses'   => $validOrderStatuses,
                'valid_payment_statuses' => $validPaymentStatuses,
            ]);

        } catch (PDOException $e) {
            error_log('[admin-orders GET single] DB error: ' . $e->getMessage());
            respond(['success' => false, 'message' => 'Failed to load order details.'], 500);
        }
    }

    // ── Orders List View ──────────────────────────────────────────
    $statusFilter  = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : '';
    $paymentFilter = isset($_GET['payment']) ? strtolower(trim((string) $_GET['payment'])) : '';
    $searchTerm    = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
    $dateFilter    = isset($_GET['date']) ? trim((string) $_GET['date']) : '';
    $limit         = isset($_GET['limit']) && is_numeric($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 50;
    $offset        = isset($_GET['offset']) && is_numeric($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

    try {
        $where = [];
        $params = [];

        if ($statusFilter !== '') {
            $where[] = 'o.order_status = :status';
            $params[':status'] = $statusFilter;
        }

        if ($paymentFilter !== '') {
            $where[] = 'o.payment_status = :payment';
            $params[':payment'] = $paymentFilter;
        }

        if ($dateFilter !== '') {
            $where[] = '(o.delivery_date = :fdate OR DATE(o.created_at) = :fdate)';
            $params[':fdate'] = $dateFilter;
        }

        if ($searchTerm !== '') {
            $where[] = '(o.order_number LIKE :search OR o.full_name LIKE :search OR o.email LIKE :search OR o.phone_no LIKE :search)';
            $params[':search'] = '%' . $searchTerm . '%';
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `orders` o $whereSql");
        $countStmt->execute($params);
        $totalOrdersCount = (int) $countStmt->fetchColumn();

        // Status counts breakdown for quick UI badges
        $statusCountsStmt = $pdo->query('
            SELECT 
                order_status,
                COUNT(*) AS cnt
            FROM `orders`
            GROUP BY order_status
        ');
        $statusCounts = [];
        foreach ($validOrderStatuses as $st) {
            $statusCounts[$st] = 0;
        }
        while ($r = $statusCountsStmt->fetch(PDO::FETCH_ASSOC)) {
            $statusCounts[$r['order_status']] = (int) $r['cnt'];
        }

        // Orders list query
        $sql = "
            SELECT 
                o.order_id,
                o.customer_id,
                o.order_number,
                o.full_name,
                o.email,
                o.phone_no,
                o.delivery_date,
                o.delivery_time_slot,
                o.payment_method,
                o.payment_status,
                o.payhere_payment_id,
                o.subtotal,
                o.delivery_fee,
                o.total,
                o.order_status,
                o.created_at,
                COUNT(oi.order_item_id) AS item_count,
                COALESCE(SUM(oi.quantity), 0) AS total_quantity,
                GROUP_CONCAT(DISTINCT oi.cake_name ORDER BY oi.order_item_id SEPARATOR ', ') AS item_names_summary
            FROM `orders` o
            LEFT JOIN `order_item` oi ON o.order_id = oi.order_id
            $whereSql
            GROUP BY o.order_id
            ORDER BY o.order_id DESC
            LIMIT $limit OFFSET $offset
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $orders = array_map(function ($row) {
            return [
                'order_id'           => (int) $row['order_id'],
                'customer_id'        => $row['customer_id'] !== null ? (int) $row['customer_id'] : null,
                'order_number'       => (string) $row['order_number'],
                'full_name'          => (string) $row['full_name'],
                'email'              => (string) $row['email'],
                'phone_no'           => (string) $row['phone_no'],
                'delivery_date'      => (string) $row['delivery_date'],
                'delivery_time_slot' => (string) $row['delivery_time_slot'],
                'payment_method'     => (string) $row['payment_method'],
                'payment_status'     => (string) $row['payment_status'],
                'payhere_payment_id' => $row['payhere_payment_id'] ? (string) $row['payhere_payment_id'] : null,
                'subtotal'           => (float) $row['subtotal'],
                'delivery_fee'       => (float) $row['delivery_fee'],
                'total'              => (float) $row['total'],
                'order_status'       => (string) $row['order_status'],
                'created_at'         => (string) $row['created_at'],
                'item_count'         => (int) $row['item_count'],
                'total_quantity'     => (int) $row['total_quantity'],
                'items_summary'      => (string) ($row['item_names_summary'] ?? ''),
            ];
        }, $rows);

        respond([
            'success'                => true,
            'count'                  => count($orders),
            'total_orders'           => $totalOrdersCount,
            'orders'                 => $orders,
            'status_counts'          => $statusCounts,
            'valid_order_statuses'   => $validOrderStatuses,
            'valid_payment_statuses' => $validPaymentStatuses,
        ]);

    } catch (PDOException $e) {
        error_log('[admin-orders GET list] DB error: ' . $e->getMessage());
        respond(['success' => false, 'message' => 'Failed to load orders list.'], 500);
    }
}

// ─────────────────────────────────────────────────────────────────
// POST / PUT: Update Order Status or Payment Status
// ─────────────────────────────────────────────────────────────────
if ($method === 'POST' || $method === 'PUT') {
    $body = getBody();
    $action = trim((string) ($body['action'] ?? 'update_status'));

    $orderId = (int) ($body['order_id'] ?? $body['id'] ?? 0);
    $orderNumber = trim((string) ($body['order_number'] ?? ''));

    if ($orderId <= 0 && $orderNumber === '') {
        respond(['success' => false, 'message' => 'A valid order_id or order_number is required.'], 400);
    }

    // Verify order exists
    $orderStmt = $pdo->prepare('
        SELECT order_id, order_number, order_status, payment_status, total, special_instructions
        FROM `orders`
        WHERE order_id = :oid OR order_number = :onum
        LIMIT 1
    ');
    $orderStmt->execute([
        ':oid'  => $orderId ?: 0,
        ':onum' => $orderNumber ?: '',
    ]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        respond(['success' => false, 'message' => 'Order not found.'], 404);
    }

    $targetOrderId = (int) $order['order_id'];

    // ── ACTION: UPDATE ORDER STATUS ───────────────────────────────
    if ($action === 'update_status') {
        $newStatus = strtolower(trim((string) ($body['order_status'] ?? $body['status'] ?? '')));

        if (!in_array($newStatus, $validOrderStatuses, true)) {
            respond([
                'success' => false,
                'message' => "Invalid order status '{$newStatus}'. Allowed statuses: " . implode(', ', $validOrderStatuses),
                'allowed' => $validOrderStatuses,
            ], 400);
        }

        try {
            $pdo->beginTransaction();

            // Lock authoritative order row inside transaction to eliminate concurrent race conditions
            $lockStmt = $pdo->prepare('
                SELECT order_id, order_number, order_status, special_instructions 
                FROM `orders` 
                WHERE order_id = ? 
                FOR UPDATE
            ');
            $lockStmt->execute([$targetOrderId]);
            $lockedOrder = $lockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$lockedOrder) {
                $pdo->rollBack();
                respond(['success' => false, 'message' => 'Order not found.'], 404);
            }

            // Guard: Block advancing shortage-flagged orders to fulfillment statuses using authoritative locked state
            $blockedStatuses = ['confirmed', 'in_progress', 'ready', 'delivered'];
            $instructions = (string) ($lockedOrder['special_instructions'] ?? '');
            $hasShortageAlert = stripos($instructions, '[INVENTORY SHORTAGE ALERT:') !== false;

            if ($hasShortageAlert && in_array($newStatus, $blockedStatuses, true)) {
                $pdo->rollBack();
                respond([
                    'success'   => false,
                    'message'   => "Cannot advance order #{$lockedOrder['order_number']} to '{$newStatus}': This order has an unresolved system-generated inventory shortage alert. Stock was depleted at payment time. Administrative inventory resolution is required before this order can be confirmed or fulfilled.",
                    'order_id'  => $targetOrderId,
                    'shortage'  => true,
                ], 422);
            }

            $stmt = $pdo->prepare('UPDATE `orders` SET order_status = ? WHERE order_id = ?');
            $stmt->execute([$newStatus, $targetOrderId]);

            $pdo->commit();

            respond([
                'success'      => true,
                'message'      => "Order #{$lockedOrder['order_number']} status updated to '{$newStatus}'.",
                'order_id'     => $targetOrderId,
                'order_number' => $lockedOrder['order_number'],
                'old_status'   => $lockedOrder['order_status'],
                'order_status' => $newStatus,
            ]);

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[admin-orders update_status] DB error: ' . $e->getMessage());
            respond(['success' => false, 'message' => 'Failed to update order status.'], 500);
        }
    }

    // ── ACTION: UPDATE PAYMENT STATUS ─────────────────────────────
    if ($action === 'update_payment_status') {
        $newPayStatus = strtolower(trim((string) ($body['payment_status'] ?? $body['payment'] ?? '')));

        if (!in_array($newPayStatus, $validPaymentStatuses, true)) {
            respond([
                'success' => false,
                'message' => "Invalid payment status '{$newPayStatus}'. Allowed: " . implode(', ', $validPaymentStatuses),
                'allowed' => $validPaymentStatuses,
            ], 400);
        }

        try {
            $pdo->beginTransaction();

            // 1. Lock the order row to read authoritative status under transaction
            $lockStmt = $pdo->prepare('
                SELECT order_id, order_number, order_status, payment_status, payment_method, payhere_payment_id
                FROM `orders`
                WHERE order_id = ?
                FOR UPDATE
            ');
            $lockStmt->execute([$targetOrderId]);
            $currentOrder = $lockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentOrder) {
                $pdo->rollBack();
                respond(['success' => false, 'message' => 'Order not found.'], 404);
            }

            // 2. Reject marking payment as paid if the order is cancelled
            if ($newPayStatus === 'paid' && $currentOrder['order_status'] === 'cancelled') {
                $pdo->rollBack();
                respond(['success' => false, 'message' => 'Cannot mark payment as paid for a cancelled order.'], 400);
            }

            // 3. Reject reverting a paid order back to pending (prevents re-deduction vulnerabilities)
            if ($currentOrder['payment_status'] === 'paid' && $newPayStatus === 'pending') {
                $pdo->rollBack();
                respond(['success' => false, 'message' => 'Cannot revert an already paid order to pending. If the payment has been cancelled or refunded externally, you may update the local payment status to cancelled or charged_back. IMPORTANT: Changing this status does not issue a PayHere refund. Process any card refund separately through your PayHere Merchant Portal.'], 400);
            }

            // 4. Online card payments require verified gateway evidence and cannot be marked paid without it
            $verifiedPayHereId = null;
            if ($newPayStatus === 'paid' && $currentOrder['payment_status'] !== 'paid') {
                if ($currentOrder['payment_method'] === 'card') {
                    $evidenceId = trim((string) ($body['payhere_payment_id'] ?? $body['payment_reference'] ?? $body['evidence'] ?? $currentOrder['payhere_payment_id'] ?? ''));
                    if ($evidenceId === '') {
                        $pdo->rollBack();
                        respond([
                            'success' => false,
                            'message' => 'Manual payment confirmation for online card orders requires verified gateway evidence. Please provide the PayHere Payment ID (from your PayHere Merchant Portal).',
                        ], 422);
                    }
                    $verifiedPayHereId = $evidenceId;
                }

                // Verify stock availability and deduct conditionally for items
                $itemsStmt = $pdo->prepare('
                    SELECT cake_id, SUM(quantity) AS total_qty 
                    FROM `order_item` 
                    WHERE order_id = ? AND cake_id IS NOT NULL 
                    GROUP BY cake_id 
                    ORDER BY cake_id ASC
                ');
                $itemsStmt->execute([$targetOrderId]);
                $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                $cakeLockStmt = $pdo->prepare('SELECT cake_id, cake_name, stock_quantity FROM `cake` WHERE cake_id = ? FOR UPDATE');
                $shortages = [];
                $itemsToDeduct = [];

                foreach ($items as $it) {
                    $cid = (int) $it['cake_id'];
                    $reqQty = (int) $it['total_qty'];

                    $cakeLockStmt->execute([$cid]);
                    $cakeRow = $cakeLockStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$cakeRow) {
                        $shortages[] = "Product #{$cid} not found in catalog";
                        continue;
                    }

                    if ($cakeRow['stock_quantity'] !== null) {
                        $avail = (int) $cakeRow['stock_quantity'];
                        if ($avail < $reqQty) {
                            $shortages[] = "'{$cakeRow['cake_name']}' (available: {$avail}, requested: {$reqQty})";
                        } else {
                            $itemsToDeduct[] = [
                                'cake_id'  => $cid,
                                'quantity' => $reqQty,
                            ];
                        }
                    }
                }

                if (!empty($shortages)) {
                    $pdo->rollBack();
                    respond([
                        'success'   => false,
                        'message'   => 'Cannot confirm payment due to insufficient stock: ' . implode(', ', $shortages) . '. Adjust inventory before confirming payment.',
                        'shortages' => $shortages,
                    ], 422);
                }

                $stockStmt = $pdo->prepare('
                    UPDATE `cake` 
                    SET stock_quantity = stock_quantity - :qty
                    WHERE cake_id = :cake_id AND stock_quantity IS NOT NULL AND stock_quantity >= :min_qty
                ');
                foreach ($itemsToDeduct as $it) {
                    $stockStmt->execute([
                        ':qty'     => (int) $it['quantity'],
                        ':min_qty' => (int) $it['quantity'],
                        ':cake_id' => (int) $it['cake_id'],
                    ]);
                }
            }

            if ($verifiedPayHereId !== null) {
                $stmt = $pdo->prepare('UPDATE `orders` SET payment_status = ?, payhere_payment_id = ? WHERE order_id = ?');
                $stmt->execute([$newPayStatus, $verifiedPayHereId, $targetOrderId]);
            } else {
                $stmt = $pdo->prepare('UPDATE `orders` SET payment_status = ? WHERE order_id = ?');
                $stmt->execute([$newPayStatus, $targetOrderId]);
            }

            $pdo->commit();

            respond([
                'success'            => true,
                'message'            => "Order #{$order['order_number']} payment status updated to '{$newPayStatus}'.",
                'order_id'           => $targetOrderId,
                'order_number'       => $order['order_number'],
                'payment_status'     => $newPayStatus,
                'payhere_payment_id' => $verifiedPayHereId ?? $currentOrder['payhere_payment_id'] ?? null,
            ]);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[admin-orders update_payment_status] DB error: ' . $e->getMessage());
            respond(['success' => false, 'message' => 'Failed to update payment status.'], 500);
        }
    }

    respond(['success' => false, 'message' => "Unknown action '{$action}'. Valid actions: update_status, update_payment_status."], 400);
}

respond(['success' => false, 'message' => 'Method not allowed.'], 405);
