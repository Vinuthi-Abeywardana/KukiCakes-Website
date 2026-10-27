<?php
/**
 * KúkiCakes — GET /api/my-orders.php
 *
 * Customer-only Order History API.
 * Returns orders placed by the currently authenticated customer session.
 *
 * Requirements:
 * 1. Accepts GET method only (405 for other methods).
 * 2. Enforces requireAuth() — rejects guests (401) and administrators (403).
 * 3. Never accepts customer_id from request parameters (strictly server-session driven).
 * 4. Fetches orders newest first with associated line items (avoids N+1 query pattern).
 * 5. Safely handles missing/nullable relations and provides local fallback images.
 * 6. Maps 'in_progress' to 'preparing' for UI consistency while preserving raw_status and 'ready'.
 */

require_once __DIR__ . '/bootstrap.php';

// 1. Enforce GET method only
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['success' => false, 'message' => 'Method not allowed. Use GET.'], 405);
}

// 2. Enforce customer authentication
$customerId = requireAuth();

try {
    // 3. Fetch orders for this customer only
    $orderStmt = $pdo->prepare('
        SELECT 
            o.order_id,
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
            o.subtotal,
            o.delivery_fee,
            o.total,
            o.order_status,
            o.created_at
        FROM `orders` o
        WHERE o.customer_id = ?
        ORDER BY o.order_id DESC
    ');
    $orderStmt->execute([$customerId]);
    $orderRows = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orderRows)) {
        respond([
            'success' => true,
            'count'   => 0,
            'orders'  => [],
        ]);
    }

    // 4. Efficiently fetch all line items for retrieved orders in a single query (avoid N+1)
    $orderIds = array_map(fn($row) => (int) $row['order_id'], $orderRows);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

    $itemStmt = $pdo->prepare("
        SELECT 
            oi.order_item_id,
            oi.order_id,
            oi.cake_id,
            oi.flavor_id,
            oi.cake_name,
            oi.unit_price,
            oi.quantity,
            oi.weight,
            oi.special_note,
            COALESCE(fl.flavor_name, 'Standard') AS flavor_name,
            COALESCE(ck.image, 'docs/logo1_1.png') AS cake_image
        FROM `order_item` oi
        LEFT JOIN `cake` ck ON oi.cake_id = ck.cake_id
        LEFT JOIN `flavor` fl ON oi.flavor_id = fl.flavor_id
        WHERE oi.order_id IN ($placeholders)
        ORDER BY oi.order_id DESC, oi.order_item_id ASC
    ");
    $itemStmt->execute($orderIds);
    $itemRows = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    // Group items by order_id
    $itemsByOrder = [];
    $baseProjectDir = dirname(__DIR__);

    foreach ($itemRows as $item) {
        $oid = (int) $item['order_id'];
        $unitPrice = (float) $item['unit_price'];
        $qty = (int) $item['quantity'];

        // Resolve verified local image path with extension tolerance or logo fallback
        $imgCandidate = (string) ($item['cake_image'] ?? '');
        $finalImg = 'docs/logo1_1.png';
        if ($imgCandidate !== '') {
            if (file_exists($baseProjectDir . '/' . $imgCandidate)) {
                $finalImg = $imgCandidate;
            } elseif (str_ends_with($imgCandidate, '.jpg') && file_exists($baseProjectDir . '/' . substr($imgCandidate, 0, -4) . '.jpeg')) {
                $finalImg = substr($imgCandidate, 0, -4) . '.jpeg';
            } elseif (str_ends_with($imgCandidate, '.jpeg') && file_exists($baseProjectDir . '/' . substr($imgCandidate, 0, -5) . '.jpg')) {
                $finalImg = substr($imgCandidate, 0, -5) . '.jpg';
            }
        }

        $itemsByOrder[$oid][] = [
            'order_item_id' => (int) $item['order_item_id'],
            'cake_id'       => $item['cake_id'] !== null ? (int) $item['cake_id'] : null,
            'cake'          => (string) $item['cake_name'],
            'unit_price'    => $unitPrice,
            'qty'           => $qty,
            'weight'        => (string) ($item['weight'] ?: 'Standard'),
            'flavor'        => (string) ($item['flavor_name'] ?: 'Standard'),
            'note'          => (string) ($item['special_note'] ?? ''),
            'img'           => $finalImg,
            'line_total'    => round($unitPrice * $qty, 2),
        ];
    }

    // 5. Build structured customer orders response
    $orders = [];
    foreach ($orderRows as $o) {
        $oid = (int) $o['order_id'];
        $items = $itemsByOrder[$oid] ?? [];

        // Format dates cleanly for UI display
        $createdTs = strtotime($o['created_at']);
        $orderDateFormatted = $createdTs ? date('j M Y', $createdTs) : (string) $o['created_at'];

        $deliveryTs = strtotime($o['delivery_date']);
        $deliveryDateFormatted = $deliveryTs ? date('j M Y', $deliveryTs) : (string) $o['delivery_date'];

        $rawStatus = (string) $o['order_status'];
        // Map in_progress to preparing for UI tab matching; preserve ready, pending, confirmed, delivered, cancelled
        $displayStatus = ($rawStatus === 'in_progress') ? 'preparing' : $rawStatus;

        $primaryItem = $items[0] ?? null;

        $orders[] = [
            'id'                 => (string) $o['order_number'],
            'order_id'           => $oid,
            'date'               => $orderDateFormatted,
            'created_at'         => (string) $o['created_at'],
            'deliveryDate'       => $deliveryDateFormatted,
            'delivery_date'      => (string) $o['delivery_date'],
            'deliveryTimeSlot'   => (string) $o['delivery_time_slot'],
            'address'            => (string) $o['delivery_address'],
            'recipient_name'     => (string) $o['full_name'],
            'recipient_phone'    => (string) $o['phone_no'],
            'special_instructions' => (string) ($o['special_instructions'] ?? ''),
            'payment_method'     => (string) $o['payment_method'],
            'payment_status'     => (string) $o['payment_status'],
            'subtotal'           => (float) $o['subtotal'],
            'delivery_fee'       => (float) $o['delivery_fee'],
            'total'              => (float) $o['total'],
            'status'             => $displayStatus,
            'raw_status'         => $rawStatus,
            'item_count'         => count($items),
            'items'              => $items,
            // Backwards-compatible primary fields for single-cake previews
            'cake'               => $primaryItem ? $primaryItem['cake'] : 'Artisan Cake',
            'img'                => $primaryItem ? $primaryItem['img'] : 'docs/logo1_1.png',
            'qty'                => $primaryItem ? $primaryItem['qty'] : 1,
            'weight'             => $primaryItem ? $primaryItem['weight'] : 'Standard',
            'flavor'             => $primaryItem ? $primaryItem['flavor'] : 'Standard',
        ];
    }

    respond([
        'success' => true,
        'count'   => count($orders),
        'orders'  => $orders,
    ]);

} catch (PDOException $e) {
    error_log('[KukiCakes my-orders] DB error: ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Could not retrieve your orders. Please try again later.'], 500);
} catch (Throwable $e) {
    error_log('[KukiCakes my-orders] General error: ' . $e->getMessage());
    respond(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
}
