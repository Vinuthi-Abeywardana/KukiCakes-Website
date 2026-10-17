<?php
/**
 * KúkiCakes — GET & POST /api/admin-inventory.php
 *
 * Administrator Inventory & Stock Management API.
 *
 * Requirements:
 * 1. Enforces requireAdmin() — rejects guests (401) and customers (403).
 * 2. GET:
 *    - Lists products with stock quantities and health indicators:
 *      'in_stock', 'low_stock', 'out_of_stock', 'untracked'.
 *    - Supports ?low_stock=1, ?out_of_stock=1, ?untracked=1, ?search=..., ?cat=...
 * 3. POST / PUT:
 *    - action: 'update_stock' — sets stock_quantity for a specific cake_id.
 *    - action: 'adjust_stock' — adds/subtracts delta from stock_quantity.
 *    - action: 'bulk_update' — updates stock for multiple products in a transaction.
 */

require_once __DIR__ . '/bootstrap.php';

// Enforce strict administrator authorization
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ─────────────────────────────────────────────────────────────────
// GET: Query Inventory Items
// ─────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $search        = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
    $catParam      = isset($_GET['cat']) ? trim((string) $_GET['cat']) : '';
    $lowStockOnly  = !empty($_GET['low_stock']);
    $outOfStockOnly= !empty($_GET['out_of_stock']);
    $untrackedOnly = !empty($_GET['untracked']);
    $availFilter   = isset($_GET['availability']) && in_array($_GET['availability'], ['0', '1', 0, 1], true) ? (int) $_GET['availability'] : null;

    try {
        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(c.cake_name LIKE :search OR c.description LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        if ($availFilter !== null) {
            $where[] = 'c.availability = :avail';
            $params[':avail'] = $availFilter;
        }

        if ($catParam !== '') {
            if (is_numeric($catParam)) {
                $where[] = 'c.cake_id IN (SELECT cake_id FROM `cake_category` WHERE category_id = :cid)';
                $params[':cid'] = (int) $catParam;
            }
        }

        if ($lowStockOnly) {
            $where[] = 'c.stock_quantity IS NOT NULL AND c.stock_quantity <= 5 AND c.stock_quantity > 0';
        }

        if ($outOfStockOnly) {
            $where[] = 'c.stock_quantity = 0';
        }

        if ($untrackedOnly) {
            $where[] = 'c.stock_quantity IS NULL';
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Global inventory statistics
        $statsStmt = $pdo->query('
            SELECT 
                COUNT(*) AS total_items,
                COUNT(CASE WHEN stock_quantity IS NOT NULL AND stock_quantity <= 5 AND stock_quantity > 0 THEN 1 END) AS low_stock_count,
                COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) AS out_of_stock_count,
                COUNT(CASE WHEN stock_quantity > 5 THEN 1 END) AS healthy_stock_count,
                COUNT(CASE WHEN stock_quantity IS NULL THEN 1 END) AS untracked_count,
                COALESCE(SUM(stock_quantity), 0) AS total_units_in_stock
            FROM `cake`
        ');
        $invStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

        // Inventory list
        $sql = "
            SELECT 
                c.cake_id,
                c.cake_name,
                c.price,
                c.image,
                c.availability,
                c.stock_quantity,
                c.badge,
                GROUP_CONCAT(DISTINCT cat.category_name ORDER BY cat.category_id SEPARATOR ', ') AS categories_list
            FROM `cake` c
            LEFT JOIN `cake_category` cc ON c.cake_id = cc.cake_id
            LEFT JOIN `category` cat ON cc.category_id = cat.category_id
            $whereSql
            GROUP BY c.cake_id
            ORDER BY 
                CASE 
                    WHEN c.stock_quantity = 0 THEN 1
                    WHEN c.stock_quantity IS NOT NULL AND c.stock_quantity <= 5 THEN 2
                    WHEN c.stock_quantity IS NOT NULL THEN 3
                    ELSE 4
                END ASC,
                c.stock_quantity ASC,
                c.cake_name ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(function ($row) {
            $sq = $row['stock_quantity'] !== null ? (int) $row['stock_quantity'] : null;
            $status = 'untracked';
            if ($sq !== null) {
                if ($sq === 0) {
                    $status = 'out_of_stock';
                } elseif ($sq <= 5) {
                    $status = 'low_stock';
                } else {
                    $status = 'in_stock';
                }
            }

            return [
                'cake_id'        => (int) $row['cake_id'],
                'cake_name'      => (string) $row['cake_name'],
                'price'          => (float) $row['price'],
                'image'          => (string) ($row['image'] ?? ''),
                'availability'   => (int) $row['availability'],
                'stock_quantity' => $sq,
                'stock_status'   => $status,
                'badge'          => $row['badge'] ? (string) $row['badge'] : null,
                'categories'     => (string) ($row['categories_list'] ?? ''),
            ];
        }, $rows);

        respond([
            'success' => true,
            'summary' => [
                'total_items'          => (int) ($invStats['total_items'] ?? 0),
                'low_stock_count'      => (int) ($invStats['low_stock_count'] ?? 0),
                'out_of_stock_count'   => (int) ($invStats['out_of_stock_count'] ?? 0),
                'healthy_stock_count'  => (int) ($invStats['healthy_stock_count'] ?? 0),
                'untracked_count'      => (int) ($invStats['untracked_count'] ?? 0),
                'total_units_in_stock' => (int) ($invStats['total_units_in_stock'] ?? 0),
            ],
            'count' => count($items),
            'items' => $items,
        ]);

    } catch (PDOException $e) {
        error_log('[admin-inventory GET] DB error: ' . $e->getMessage());
        respond(['success' => false, 'message' => 'Failed to load inventory.'], 500);
    }
}

// ─────────────────────────────────────────────────────────────────
// POST / PUT: Update, Adjust, or Bulk Update Stock
// ─────────────────────────────────────────────────────────────────
if ($method === 'POST' || $method === 'PUT') {
    $body = getBody();
    $action = trim((string) ($body['action'] ?? 'update_stock'));

    // Automatically detect bulk vs single update
    if (isset($body['items']) && is_array($body['items'])) {
        $action = 'bulk_update';
    }

    // ── ACTION: UPDATE SPECIFIC PRODUCT STOCK ─────────────────────
    if ($action === 'update_stock') {
        $cakeId = (int) ($body['cake_id'] ?? $body['id'] ?? 0);
        if ($cakeId <= 0) {
            respond(['success' => false, 'message' => 'A valid product cake_id is required.'], 400);
        }

        // Verify product exists
        $checkStmt = $pdo->prepare('SELECT cake_id, cake_name, stock_quantity FROM `cake` WHERE cake_id = ?');
        $checkStmt->execute([$cakeId]);
        $cake = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$cake) {
            respond(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $stock = null;
        if (array_key_exists('stock_quantity', $body)) {
            $rawStock = $body['stock_quantity'];
            if ($rawStock === null || $rawStock === '' || strtolower((string)$rawStock) === 'null') {
                $stock = null;
            } elseif (!is_numeric($rawStock) || (int) $rawStock < 0) {
                respond(['success' => false, 'message' => 'Stock quantity must be a non-negative integer or null.'], 400);
            } else {
                $stock = (int) $rawStock;
            }
        } else {
            respond(['success' => false, 'message' => 'stock_quantity parameter is required.'], 400);
        }

        try {
            $stmt = $pdo->prepare('UPDATE `cake` SET stock_quantity = ? WHERE cake_id = ?');
            $stmt->execute([$stock, $cakeId]);

            $stockStatus = 'untracked';
            if ($stock !== null) {
                if ($stock === 0) {
                    $stockStatus = 'out_of_stock';
                } elseif ($stock <= 5) {
                    $stockStatus = 'low_stock';
                } else {
                    $stockStatus = 'in_stock';
                }
            }

            respond([
                'success'        => true,
                'message'        => "Stock quantity for '{$cake['cake_name']}' updated to " . ($stock !== null ? $stock : 'untracked') . '.',
                'cake_id'        => $cakeId,
                'stock_quantity' => $stock,
                'stock_status'   => $stockStatus,
            ]);

        } catch (PDOException $e) {
            error_log('[admin-inventory update_stock] DB error: ' . $e->getMessage());
            respond(['success' => false, 'message' => 'Failed to update stock.'], 500);
        }
    }

    // ── ACTION: ADJUST STOCK (INCREMENT / DECREMENT) ──────────────
    if ($action === 'adjust_stock') {
        $cakeId = (int) ($body['cake_id'] ?? $body['id'] ?? 0);
        $delta  = (int) ($body['adjustment'] ?? $body['delta'] ?? 0);

        if ($cakeId <= 0) {
            respond(['success' => false, 'message' => 'A valid cake_id is required.'], 400);
        }
        if ($delta === 0) {
            respond(['success' => false, 'message' => 'Non-zero adjustment value is required.'], 400);
        }

        $checkStmt = $pdo->prepare('SELECT cake_id, cake_name, stock_quantity FROM `cake` WHERE cake_id = ?');
        $checkStmt->execute([$cakeId]);
        $cake = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$cake) {
            respond(['success' => false, 'message' => 'Product not found.'], 404);
        }

        if ($cake['stock_quantity'] === null) {
            respond([
                'success' => false,
                'message' => "Product '{$cake['cake_name']}' is untracked (stock quantity is NULL). Set an explicit stock quantity using update_stock before adjusting.",
            ], 400);
        }

        try {
            $stmt = $pdo->prepare('
                UPDATE `cake` 
                SET stock_quantity = GREATEST(0, stock_quantity + :delta) 
                WHERE cake_id = :cake_id AND stock_quantity IS NOT NULL
            ');
            $stmt->execute([':delta' => $delta, ':cake_id' => $cakeId]);

            // Query authoritative updated stock
            $freshStmt = $pdo->prepare('SELECT stock_quantity FROM `cake` WHERE cake_id = ?');
            $freshStmt->execute([$cakeId]);
            $newStock = (int) $freshStmt->fetchColumn();
            $oldStock = (int) $cake['stock_quantity'];

            respond([
                'success'        => true,
                'message'        => "Stock for '{$cake['cake_name']}' adjusted from {$oldStock} to {$newStock}.",
                'cake_id'        => $cakeId,
                'old_stock'      => $oldStock,
                'stock_quantity' => $newStock,
            ]);

        } catch (PDOException $e) {
            error_log('[admin-inventory adjust_stock] DB error: ' . $e->getMessage());
            respond(['success' => false, 'message' => 'Failed to adjust stock.'], 500);
        }
    }

    // ── ACTION: BULK STOCK UPDATE ─────────────────────────────────
    if ($action === 'bulk_update') {
        $items = $body['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            respond(['success' => false, 'message' => 'Array of items with cake_id and stock_quantity is required.'], 400);
        }

        // Validate items
        $validated = [];
        foreach ($items as $idx => $it) {
            $cid = (int) ($it['cake_id'] ?? 0);
            if ($cid <= 0) {
                respond(['success' => false, 'message' => "Invalid cake_id at item index $idx."], 400);
            }

            $rawQty = $it['stock_quantity'] ?? null;
            $qty = null;
            if ($rawQty !== null && $rawQty !== '' && strtolower((string)$rawQty) !== 'null') {
                if (!is_numeric($rawQty) || (int) $rawQty < 0) {
                    respond(['success' => false, 'message' => "Invalid stock_quantity for cake_id $cid: must be non-negative integer."], 400);
                }
                $qty = (int) $rawQty;
            }

            $validated[] = ['cake_id' => $cid, 'stock_quantity' => $qty];
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('UPDATE `cake` SET stock_quantity = ? WHERE cake_id = ?');
            $updatedCount = 0;
            foreach ($validated as $v) {
                $stmt->execute([$v['stock_quantity'], $v['cake_id']]);
                $updatedCount++;
            }

            $pdo->commit();

            respond([
                'success'       => true,
                'message'       => "Bulk stock update completed. Updated {$updatedCount} product(s).",
                'updated_count' => $updatedCount,
            ]);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[admin-inventory bulk_update] DB error: ' . $e->getMessage());
            respond(['success' => false, 'message' => 'Failed to perform bulk stock update.'], 500);
        }
    }

    respond(['success' => false, 'message' => "Unknown action '{$action}'. Valid actions: update_stock, adjust_stock, bulk_update."], 400);
}

respond(['success' => false, 'message' => 'Method not allowed.'], 405);
