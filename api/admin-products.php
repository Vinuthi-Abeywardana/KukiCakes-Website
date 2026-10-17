<?php
/**
 * KúkiCakes — GET & POST /api/admin-products.php
 *
 * Administrator Product Management API.
 *
 * Requirements:
 * 1. Enforces requireAdmin() — rejects guests (401) and customers (403).
 * 2. GET:
 *    - Lists all products (including inactive/hidden).
 *    - Supports ?id=N, ?search=..., ?cat=..., ?availability=..., ?stock=...
 *    - Returns structured categories array, availability, stock_quantity, and price.
 *    - Returns all system categories for form population.
 * 3. POST / PUT:
 *    - action: 'create' — inserts product and cake_category mappings in a transaction.
 *    - action: 'update' — updates details, price, stock, categories in a transaction.
 *    - action: 'toggle_availability' — activates/deactivates product.
 *    - action: 'delete' — safely deletes or deactivates if referenced in orders.
 */

require_once __DIR__ . '/bootstrap.php';

// Enforce strict administrator authorization
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Authoritative category slug mapping
$categorySlugs = [
    1 => 'anniversary',
    2 => 'birthday',
    3 => 'christmas',
    4 => 'cupcake',
    5 => 'engagement',
    6 => 'graduation',
    7 => 'number',
    8 => 'party',
    9 => 'wedding',
    10 => 'others',
];
$slugToId = array_flip($categorySlugs);

// ─────────────────────────────────────────────────────────────────
// GET: List or Retrieve Products
// ─────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $productId      = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : null;
    $categoryParam  = isset($_GET['cat']) ? strtolower(trim((string) $_GET['cat'])) : '';
    $searchTerm     = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
    $availFilter    = isset($_GET['availability']) && in_array($_GET['availability'], ['0', '1', 0, 1], true) ? (int) $_GET['availability'] : null;
    $stockFilter    = isset($_GET['stock']) ? strtolower(trim((string) $_GET['stock'])) : '';
    $limit          = isset($_GET['limit']) && is_numeric($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 100;
    $offset         = isset($_GET['offset']) && is_numeric($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

    try {
        // Fetch all categories for reference
        $allCatsStmt = $pdo->query('SELECT category_id, category_name FROM `category` ORDER BY category_id ASC');
        $allCats = array_map(function ($c) use ($categorySlugs) {
            $cid = (int) $c['category_id'];
            return [
                'category_id'   => $cid,
                'category_name' => (string) $c['category_name'],
                'slug'          => $categorySlugs[$cid] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $c['category_name'])),
            ];
        }, $allCatsStmt->fetchAll(PDO::FETCH_ASSOC));

        // Build product query
        $where = [];
        $params = [];

        if ($productId !== null) {
            $where[] = 'c.cake_id = :product_id';
            $params[':product_id'] = $productId;
        }

        if ($availFilter !== null) {
            $where[] = 'c.availability = :avail';
            $params[':avail'] = $availFilter;
        }

        if ($categoryParam !== '') {
            $targetCatId = is_numeric($categoryParam) ? (int) $categoryParam : ($slugToId[$categoryParam] ?? null);
            if ($targetCatId !== null) {
                $where[] = 'c.cake_id IN (SELECT cake_id FROM `cake_category` WHERE category_id = :cat_id)';
                $params[':cat_id'] = $targetCatId;
            } else {
                $where[] = '1 = 0';
            }
        }

        if ($searchTerm !== '') {
            $where[] = '(c.cake_name LIKE :search_name OR c.description LIKE :search_desc)';
            $params[':search_name'] = '%' . $searchTerm . '%';
            $params[':search_desc'] = '%' . $searchTerm . '%';
        }

        if ($stockFilter === 'low') {
            $where[] = 'c.stock_quantity IS NOT NULL AND c.stock_quantity <= 5 AND c.stock_quantity > 0';
        } elseif ($stockFilter === 'out') {
            $where[] = 'c.stock_quantity = 0';
        } elseif ($stockFilter === 'in') {
            $where[] = 'c.stock_quantity IS NOT NULL AND c.stock_quantity > 5';
        } elseif ($stockFilter === 'untracked') {
            $where[] = 'c.stock_quantity IS NULL';
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total matching
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `cake` c $whereSql");
        $countStmt->execute($params);
        $totalCount = (int) $countStmt->fetchColumn();

        // Query product details with aggregated categories
        $sql = "
            SELECT 
                c.cake_id,
                c.cake_name,
                c.description,
                c.price,
                c.image,
                c.availability,
                c.stock_quantity,
                c.badge,
                c.created_at,
                GROUP_CONCAT(DISTINCT cat.category_id ORDER BY cat.category_id SEPARATOR ',') AS category_ids,
                GROUP_CONCAT(DISTINCT cat.category_name ORDER BY cat.category_id SEPARATOR ',') AS category_names
            FROM `cake` c
            LEFT JOIN `cake_category` cc ON c.cake_id = cc.cake_id
            LEFT JOIN `category` cat ON cc.category_id = cat.category_id
            $whereSql
            GROUP BY c.cake_id
            ORDER BY c.cake_id DESC
            LIMIT $limit OFFSET $offset
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $products = [];
        foreach ($rows as $row) {
            $catIds = !empty($row['category_ids']) ? array_map('intval', explode(',', $row['category_ids'])) : [];
            $catNames = !empty($row['category_names']) ? explode(',', $row['category_names']) : [];

            $structuredCats = [];
            $slugs = [];
            foreach ($catIds as $idx => $cid) {
                $cname = $catNames[$idx] ?? '';
                $slug = $categorySlugs[$cid] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $cname));
                $slugs[] = $slug;
                $structuredCats[] = [
                    'id'   => $cid,
                    'name' => $cname,
                    'slug' => $slug,
                ];
            }

            $stockQty = $row['stock_quantity'] !== null ? (int) $row['stock_quantity'] : null;
            $stockStatus = 'untracked';
            if ($stockQty !== null) {
                if ($stockQty === 0) {
                    $stockStatus = 'out_of_stock';
                } elseif ($stockQty <= 5) {
                    $stockStatus = 'low_stock';
                } else {
                    $stockStatus = 'in_stock';
                }
            }

            $products[] = [
                'id'             => (int) $row['cake_id'],
                'cake_id'        => (int) $row['cake_id'],
                'name'           => (string) $row['cake_name'],
                'cake_name'      => (string) $row['cake_name'],
                'desc'           => (string) ($row['description'] ?? ''),
                'description'    => (string) ($row['description'] ?? ''),
                'price'          => (float) $row['price'],
                'img'            => (string) ($row['image'] ?? ''),
                'image'          => (string) ($row['image'] ?? ''),
                'availability'   => (int) $row['availability'],
                'is_active'      => ((int) $row['availability']) === 1,
                'stock_quantity' => $stockQty,
                'stock_status'   => $stockStatus,
                'badge'          => $row['badge'] ? (string) $row['badge'] : null,
                'created_at'     => (string) $row['created_at'],
                'categories'     => $structuredCats,
                'category_ids'   => $catIds,
                'cat'            => implode(' ', $slugs),
            ];
        }

        // If single product requested (?id=N)
        if ($productId !== null) {
            if (empty($products)) {
                respond(['success' => false, 'message' => 'Product not found.'], 404);
            }
            respond([
                'success'    => true,
                'product'    => $products[0],
                'categories' => $allCats,
            ]);
        }

        respond([
            'success'     => true,
            'count'       => count($products),
            'total_count' => $totalCount,
            'products'    => $products,
            'categories'  => $allCats,
        ]);

    } catch (PDOException $e) {
        error_log('[admin-products GET] DB error: ' . $e->getMessage());
        respond(['success' => false, 'message' => 'Failed to load products.'], 500);
    }
}

// ─────────────────────────────────────────────────────────────────
// POST / PUT: Create, Update, Toggle Availability, or Delete
// ─────────────────────────────────────────────────────────────────
if ($method === 'POST' || $method === 'PUT') {
    $body = getBody();
    $action = trim((string) ($body['action'] ?? ''));

    // Automatically determine action if not explicitly given
    if ($action === '') {
        if (!empty($body['cake_id']) || !empty($body['id'])) {
            $action = 'update';
        } else {
            $action = 'create';
        }
    }

    // ── ACTION: CREATE PRODUCT ────────────────────────────────────
    if ($action === 'create') {
        $cakeName    = trim((string) ($body['cake_name'] ?? $body['name'] ?? ''));
        $description = trim((string) ($body['description'] ?? $body['desc'] ?? ''));
        $priceRaw    = $body['price'] ?? null;
        $image       = trim((string) ($body['image'] ?? $body['img'] ?? ''));
        $badge       = trim((string) ($body['badge'] ?? ''));
        $avail       = isset($body['availability']) ? (int) $body['availability'] : 1;
        $stockRaw    = isset($body['stock_quantity']) && $body['stock_quantity'] !== '' ? $body['stock_quantity'] : null;
        $catIds      = isset($body['category_ids']) && is_array($body['category_ids']) ? array_map('intval', $body['category_ids']) : [];

        // Validation
        if (strlen($cakeName) < 2 || strlen($cakeName) > 150) {
            respond(['success' => false, 'message' => 'Product name must be between 2 and 150 characters.'], 400);
        }
        if ($priceRaw === null || !is_numeric($priceRaw) || (float) $priceRaw < 0) {
            respond(['success' => false, 'message' => 'A valid non-negative price is required.'], 400);
        }
        $price = (float) $priceRaw;

        if (!in_array($avail, [0, 1], true)) {
            $avail = 1;
        }

        $stock = null;
        if ($stockRaw !== null) {
            if (!is_numeric($stockRaw) || (int) $stockRaw < 0) {
                respond(['success' => false, 'message' => 'Stock quantity must be a non-negative integer.'], 400);
            }
            $stock = (int) $stockRaw;
        }

        $badgeVal = $badge !== '' ? substr($badge, 0, 50) : null;
        $descVal  = $description !== '' ? $description : null;
        $imgVal   = $image !== '' ? $image : null;

        try {
            $pdo->beginTransaction();

            $insertStmt = $pdo->prepare('
                INSERT INTO `cake` (cake_name, description, price, image, availability, stock_quantity, badge)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $insertStmt->execute([$cakeName, $descVal, $price, $imgVal, $avail, $stock, $badgeVal]);
            $newCakeId = (int) $pdo->lastInsertId();

            // Insert category relationships
            if (!empty($catIds)) {
                $catInsert = $pdo->prepare('
                    INSERT IGNORE INTO `cake_category` (cake_id, category_id)
                    VALUES (?, ?)
                ');
                foreach ($catIds as $cid) {
                    if ($cid > 0) {
                        $catInsert->execute([$newCakeId, $cid]);
                    }
                }
            }

            $pdo->commit();

            respond([
                'success' => true,
                'message' => 'Product created successfully.',
                'product' => [
                    'cake_id'        => $newCakeId,
                    'cake_name'      => $cakeName,
                    'price'          => $price,
                    'availability'   => $avail,
                    'stock_quantity' => $stock,
                    'category_ids'   => $catIds,
                ],
            ], 201);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[admin-products CREATE] DB error: ' . $e->getMessage());
            respond(['success' => false, 'message' => 'Failed to create product due to a database error.'], 500);
        }
    }

    // ── ACTION: UPDATE PRODUCT ────────────────────────────────────
    if ($action === 'update') {
        $cakeId = (int) ($body['cake_id'] ?? $body['id'] ?? 0);
        if ($cakeId <= 0) {
            respond(['success' => false, 'message' => 'A valid product ID is required for update.'], 400);
        }

        // Verify product exists
        $checkStmt = $pdo->prepare('SELECT * FROM `cake` WHERE cake_id = ?');
        $checkStmt->execute([$cakeId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            respond(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $cakeName    = isset($body['cake_name']) ? trim((string) $body['cake_name']) : (isset($body['name']) ? trim((string) $body['name']) : $existing['cake_name']);
        $description = isset($body['description']) ? trim((string) $body['description']) : (isset($body['desc']) ? trim((string) $body['desc']) : (string) $existing['description']);
        $priceRaw    = $body['price'] ?? $existing['price'];
        $image       = isset($body['image']) ? trim((string) $body['image']) : (isset($body['img']) ? trim((string) $body['img']) : (string) $existing['image']);
        $badge       = isset($body['badge']) ? trim((string) $body['badge']) : (string) $existing['badge'];
        $avail       = isset($body['availability']) ? (int) $body['availability'] : (int) $existing['availability'];

        // Stock quantity handling:
        // Ordinary product edits must NOT overwrite real-time stock decrements from concurrent sales.
        // Keep explicit stock adjustments separate from general product information updates.
        // Stock is ONLY updated if explicitly requested via update_stock flag (or dedicated stock action).
        $existingStock = $existing['stock_quantity'] !== null ? (int) $existing['stock_quantity'] : null;
        $newStock = $existingStock;
        $shouldUpdateStock = false;

        if (!empty($body['update_stock']) && array_key_exists('stock_quantity', $body)) {
            $rawStock = $body['stock_quantity'];
            $parsedStock = null;
            if ($rawStock === null || $rawStock === '' || strtolower((string)$rawStock) === 'null') {
                $parsedStock = null;
            } elseif (!is_numeric($rawStock) || (int) $rawStock < 0) {
                respond(['success' => false, 'message' => 'Stock quantity must be a non-negative integer or null.'], 400);
            } else {
                $parsedStock = (int) $rawStock;
            }

            $newStock = $parsedStock;
            $shouldUpdateStock = true;
        }

        // Validation
        if (strlen($cakeName) < 2 || strlen($cakeName) > 150) {
            respond(['success' => false, 'message' => 'Product name must be between 2 and 150 characters.'], 400);
        }
        if (!is_numeric($priceRaw) || (float) $priceRaw < 0) {
            respond(['success' => false, 'message' => 'Price must be a valid non-negative number.'], 400);
        }
        $price = (float) $priceRaw;

        if (!in_array($avail, [0, 1], true)) {
            $avail = 1;
        }

        $badgeVal = $badge !== '' ? substr($badge, 0, 50) : null;
        $descVal  = $description !== '' ? $description : null;
        $imgVal   = $image !== '' ? $image : null;

        try {
            $pdo->beginTransaction();

            if ($shouldUpdateStock) {
                $updateStmt = $pdo->prepare('
                    UPDATE `cake`
                    SET cake_name = ?, description = ?, price = ?, image = ?, availability = ?, stock_quantity = ?, badge = ?
                    WHERE cake_id = ?
                ');
                $updateStmt->execute([$cakeName, $descVal, $price, $imgVal, $avail, $newStock, $badgeVal, $cakeId]);
            } else {
                $updateStmt = $pdo->prepare('
                    UPDATE `cake`
                    SET cake_name = ?, description = ?, price = ?, image = ?, availability = ?, badge = ?
                    WHERE cake_id = ?
                ');
                $updateStmt->execute([$cakeName, $descVal, $price, $imgVal, $avail, $badgeVal, $cakeId]);
            }

            // Update categories if explicitly provided
            if (isset($body['category_ids']) && is_array($body['category_ids'])) {
                $catIds = array_map('intval', $body['category_ids']);
                $delCats = $pdo->prepare('DELETE FROM `cake_category` WHERE cake_id = ?');
                $delCats->execute([$cakeId]);

                if (!empty($catIds)) {
                    $catInsert = $pdo->prepare('INSERT IGNORE INTO `cake_category` (cake_id, category_id) VALUES (?, ?)');
                    foreach ($catIds as $cid) {
                        if ($cid > 0) {
                            $catInsert->execute([$cakeId, $cid]);
                        }
                    }
                }
            }

            $pdo->commit();

            // Fetch authoritative current stock from DB
            $freshStockStmt = $pdo->prepare('SELECT stock_quantity FROM `cake` WHERE cake_id = ?');
            $freshStockStmt->execute([$cakeId]);
            $authStock = $freshStockStmt->fetchColumn();
            $authStock = $authStock !== null ? (int) $authStock : null;

            respond([
                'success' => true,
                'message' => 'Product updated successfully.',
                'product' => [
                    'cake_id'        => $cakeId,
                    'cake_name'      => $cakeName,
                    'price'          => $price,
                    'availability'   => $avail,
                    'stock_quantity' => $authStock,
                    'badge'          => $badgeVal,
                ],
            ], 200);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[admin-products UPDATE] DB error: ' . $e->getMessage());
            respond(['success' => false, 'message' => 'Failed to update product due to a database error.'], 500);
        }
    }

    // ── ACTION: TOGGLE AVAILABILITY ──────────────────────────────
    if ($action === 'toggle_availability' || $action === 'toggle') {
        $cakeId = (int) ($body['cake_id'] ?? $body['id'] ?? 0);
        if ($cakeId <= 0) {
            respond(['success' => false, 'message' => 'Valid product ID is required.'], 400);
        }

        $checkStmt = $pdo->prepare('SELECT availability FROM `cake` WHERE cake_id = ?');
        $checkStmt->execute([$cakeId]);
        $curr = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$curr) {
            respond(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $newAvail = isset($body['availability']) && in_array($body['availability'], [0, 1, '0', '1'], true)
            ? (int) $body['availability']
            : ($curr['availability'] == 1 ? 0 : 1);

        try {
            $stmt = $pdo->prepare('UPDATE `cake` SET availability = ? WHERE cake_id = ?');
            $stmt->execute([$newAvail, $cakeId]);

            respond([
                'success'      => true,
                'message'      => $newAvail === 1 ? 'Product activated.' : 'Product deactivated.',
                'cake_id'      => $cakeId,
                'availability' => $newAvail,
                'is_active'    => $newAvail === 1,
            ]);
        } catch (PDOException $e) {
            error_log('[admin-products TOGGLE] DB error: ' . $e->getMessage());
            respond(['success' => false, 'message' => 'Failed to update product status.'], 500);
        }
    }

    // ── ACTION: DELETE PRODUCT ────────────────────────────────────
    if ($action === 'delete') {
        $cakeId = (int) ($body['cake_id'] ?? $body['id'] ?? 0);
        if ($cakeId <= 0) {
            respond(['success' => false, 'message' => 'Valid product ID is required.'], 400);
        }

        // Check if product exists
        $checkStmt = $pdo->prepare('SELECT cake_id, cake_name FROM `cake` WHERE cake_id = ?');
        $checkStmt->execute([$cakeId]);
        $cake = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$cake) {
            respond(['success' => false, 'message' => 'Product not found.'], 404);
        }

        // Check if referenced in historical order items
        $orderItemCheck = $pdo->prepare('SELECT COUNT(*) FROM `order_item` WHERE cake_id = ?');
        $orderItemCheck->execute([$cakeId]);
        $orderCount = (int) $orderItemCheck->fetchColumn();

        if ($orderCount > 0) {
            // Soft-deactivate to protect order history integrity
            $deactStmt = $pdo->prepare('UPDATE `cake` SET availability = 0 WHERE cake_id = ?');
            $deactStmt->execute([$cakeId]);

            respond([
                'success'      => true,
                'message'      => "Product '{$cake['cake_name']}' is referenced in {$orderCount} order item(s). It has been deactivated instead of permanently deleted to preserve order history.",
                'cake_id'      => $cakeId,
                'deactivated'  => true,
                'availability' => 0,
            ], 200);
        } else {
            // Safe to permanently delete
            try {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM `cake_category` WHERE cake_id = ?')->execute([$cakeId]);
                $pdo->prepare('DELETE FROM `cake` WHERE cake_id = ?')->execute([$cakeId]);
                $pdo->commit();

                respond([
                    'success' => true,
                    'message' => "Product '{$cake['cake_name']}' has been permanently deleted.",
                    'cake_id' => $cakeId,
                    'deleted' => true,
                ], 200);
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[admin-products DELETE] DB error: ' . $e->getMessage());
                respond(['success' => false, 'message' => 'Failed to delete product.'], 500);
            }
        }
    }

    respond(['success' => false, 'message' => "Unknown action '{$action}'. Valid actions: create, update, toggle_availability, delete."], 400);
}

respond(['success' => false, 'message' => 'Method not allowed.'], 405);
