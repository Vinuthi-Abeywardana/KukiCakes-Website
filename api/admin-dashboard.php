<?php
/**
 * KúkiCakes — GET /api/admin-dashboard.php
 *
 * Administrator Dashboard Overview & Analytics API.
 *
 * Requirements:
 * 1. Enforces requireAdmin() — rejects guests (401) and customers (403).
 * 2. Calculates authoritative statistics from actual database records:
 *    - Products: total, active, inactive, low stock, out of stock
 *    - Orders: total, upcoming, today, breakdowns by order_status and payment_status
 *    - Customers: total, active accounts
 *    - Revenue: total confirmed/paid revenue, pending revenue, subtotal
 *    - Recent activity: latest orders and low-stock inventory alerts
 *
 * Responses:
 *   200 { success: true, stats: { ... }, recent_orders: [ ... ], low_stock_alerts: [ ... ] }
 *   401 { success: false, message: "Authentication required." }
 *   403 { success: false, message: "Access denied. Administrator privileges required." }
 *   405 { success: false, message: "Method not allowed. Use GET." }
 */

require_once __DIR__ . '/bootstrap.php';

// Only GET is permitted for dashboard metrics
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    respond(['success' => false, 'message' => 'Method not allowed. Use GET.'], 405);
}

// 1. Enforce strict administrator authorization
requireAdmin();

try {
    // ── 2. Product Statistics ─────────────────────────────────────
    $stmtProd = $pdo->query('
        SELECT 
            COUNT(*) AS total_products,
            COUNT(CASE WHEN availability = 1 THEN 1 END) AS active_products,
            COUNT(CASE WHEN availability = 0 THEN 1 END) AS inactive_products,
            COUNT(CASE WHEN stock_quantity IS NOT NULL AND stock_quantity <= 5 AND stock_quantity > 0 THEN 1 END) AS low_stock_products,
            COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) AS out_of_stock_products,
            COUNT(CASE WHEN stock_quantity IS NULL THEN 1 END) AS untracked_stock_products
        FROM `cake`
    ');
    $prodStats = $stmtProd->fetch(PDO::FETCH_ASSOC);

    // ── 3. Customer Statistics ────────────────────────────────────
    $stmtCust = $pdo->query('
        SELECT 
            COUNT(*) AS total_customers,
            COUNT(CASE WHEN account_status = "active" THEN 1 END) AS active_customers,
            COUNT(CASE WHEN account_status = "suspended" THEN 1 END) AS suspended_customers
        FROM `customer`
    ');
    $custStats = $stmtCust->fetch(PDO::FETCH_ASSOC);

    // ── 4. Order & Revenue Statistics ─────────────────────────────
    $stmtOrders = $pdo->query('
        SELECT 
            COUNT(*) AS total_orders,
            COUNT(CASE WHEN order_status IN ("confirmed", "in_progress", "ready") THEN 1 END) AS upcoming_orders,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) AS today_orders,
            COUNT(CASE WHEN order_status = "pending" THEN 1 END) AS pending_orders,
            COUNT(CASE WHEN order_status = "confirmed" THEN 1 END) AS confirmed_orders,
            COUNT(CASE WHEN order_status = "in_progress" THEN 1 END) AS in_progress_orders,
            COUNT(CASE WHEN order_status = "ready" THEN 1 END) AS ready_orders,
            COUNT(CASE WHEN order_status = "delivered" THEN 1 END) AS delivered_orders,
            COUNT(CASE WHEN order_status = "cancelled" THEN 1 END) AS cancelled_orders,
            COUNT(CASE WHEN payment_status = "paid" THEN 1 END) AS paid_payments_count,
            COUNT(CASE WHEN payment_status = "pending" THEN 1 END) AS pending_payments_count,
            COUNT(CASE WHEN payment_status = "failed" THEN 1 END) AS failed_payments_count,
            COALESCE(SUM(CASE WHEN payment_status = "paid" THEN total ELSE 0 END), 0) AS total_revenue,
            COALESCE(SUM(CASE WHEN payment_status = "paid" THEN subtotal ELSE 0 END), 0) AS subtotal_revenue,
            COALESCE(SUM(CASE WHEN payment_status = "paid" THEN delivery_fee ELSE 0 END), 0) AS delivery_fees_collected,
            COALESCE(SUM(CASE WHEN payment_status = "pending" AND order_status != "cancelled" THEN total ELSE 0 END), 0) AS pending_revenue
        FROM `orders`
    ');
    $ordStats = $stmtOrders->fetch(PDO::FETCH_ASSOC);

    // ── 5. Recent Orders (Latest 5) ───────────────────────────────
    $stmtRecent = $pdo->query('
        SELECT 
            o.order_id,
            o.order_number,
            o.full_name,
            o.email,
            o.phone_no,
            o.delivery_date,
            o.delivery_time_slot,
            o.payment_method,
            o.payment_status,
            o.order_status,
            o.total,
            o.created_at,
            COUNT(oi.order_item_id) AS item_count
        FROM `orders` o
        LEFT JOIN `order_item` oi ON o.order_id = oi.order_id
        GROUP BY o.order_id
        ORDER BY o.order_id DESC
        LIMIT 5
    ');
    $recentOrders = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    // Format recent orders
    $formattedRecent = array_map(function ($r) {
        return [
            'order_id'           => (int) $r['order_id'],
            'order_number'       => (string) $r['order_number'],
            'full_name'          => (string) $r['full_name'],
            'email'              => (string) $r['email'],
            'phone_no'           => (string) $r['phone_no'],
            'delivery_date'      => (string) $r['delivery_date'],
            'delivery_time_slot' => (string) $r['delivery_time_slot'],
            'payment_method'     => (string) $r['payment_method'],
            'payment_status'     => (string) $r['payment_status'],
            'order_status'       => (string) $r['order_status'],
            'total'              => (float) $r['total'],
            'item_count'         => (int) $r['item_count'],
            'created_at'         => (string) $r['created_at'],
        ];
    }, $recentOrders);

    // ── 6. Low Stock Alerts (Stock <= 5) ──────────────────────────
    $stmtLowStock = $pdo->query('
        SELECT 
            cake_id,
            cake_name,
            price,
            stock_quantity,
            availability,
            badge
        FROM `cake`
        WHERE stock_quantity IS NOT NULL AND stock_quantity <= 5
        ORDER BY stock_quantity ASC, cake_name ASC
        LIMIT 10
    ');
    $lowStockAlerts = $stmtLowStock->fetchAll(PDO::FETCH_ASSOC);

    $formattedLowStock = array_map(function ($item) {
        return [
            'cake_id'        => (int) $item['cake_id'],
            'cake_name'      => (string) $item['cake_name'],
            'price'          => (float) $item['price'],
            'stock_quantity' => (int) $item['stock_quantity'],
            'availability'   => (int) $item['availability'],
            'badge'          => $item['badge'] ? (string) $item['badge'] : null,
        ];
    }, $lowStockAlerts);

    respond([
        'success' => true,
        'stats'   => [
            'products' => [
                'total'        => (int) ($prodStats['total_products'] ?? 0),
                'active'       => (int) ($prodStats['active_products'] ?? 0),
                'inactive'     => (int) ($prodStats['inactive_products'] ?? 0),
                'low_stock'    => (int) ($prodStats['low_stock_products'] ?? 0),
                'out_of_stock' => (int) ($prodStats['out_of_stock_products'] ?? 0),
                'untracked'    => (int) ($prodStats['untracked_stock_products'] ?? 0),
            ],
            'customers' => [
                'total'     => (int) ($custStats['total_customers'] ?? 0),
                'active'    => (int) ($custStats['active_customers'] ?? 0),
                'suspended' => (int) ($custStats['suspended_customers'] ?? 0),
            ],
            'orders' => [
                'total'     => (int) ($ordStats['total_orders'] ?? 0),
                'upcoming'  => (int) ($ordStats['upcoming_orders'] ?? 0),
                'today'     => (int) ($ordStats['today_orders'] ?? 0),
                'by_status' => [
                    'pending'     => (int) ($ordStats['pending_orders'] ?? 0),
                    'confirmed'   => (int) ($ordStats['confirmed_orders'] ?? 0),
                    'in_progress' => (int) ($ordStats['in_progress_orders'] ?? 0),
                    'ready'       => (int) ($ordStats['ready_orders'] ?? 0),
                    'delivered'   => (int) ($ordStats['delivered_orders'] ?? 0),
                    'cancelled'   => (int) ($ordStats['cancelled_orders'] ?? 0),
                ],
                'by_payment' => [
                    'paid'    => (int) ($ordStats['paid_payments_count'] ?? 0),
                    'pending' => (int) ($ordStats['pending_payments_count'] ?? 0),
                    'failed'  => (int) ($ordStats['failed_payments_count'] ?? 0),
                ],
            ],
            'revenue' => [
                'total_revenue'            => (float) ($ordStats['total_revenue'] ?? 0),
                'subtotal_revenue'         => (float) ($ordStats['subtotal_revenue'] ?? 0),
                'delivery_fees_collected'  => (float) ($ordStats['delivery_fees_collected'] ?? 0),
                'pending_revenue'          => (float) ($ordStats['pending_revenue'] ?? 0),
            ],
        ],
        'recent_orders'    => $formattedRecent,
        'low_stock_alerts' => $formattedLowStock,
    ]);

} catch (PDOException $e) {
    error_log('[admin-dashboard] DB error: ' . $e->getMessage());
    respond([
        'success' => false,
        'message' => 'Failed to load dashboard metrics due to a database error.',
    ], 500);
}
