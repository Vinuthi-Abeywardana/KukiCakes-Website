<?php
/**
 * KúkiCakes — GET /api/products.php
 *
 * Public dynamic product catalog API endpoint.
 *
 * Queries MySQL tables `cake`, `cake_category`, and `category`.
 *
 * Supported Query Parameters:
 *   - ?id={cake_id}        : Fetch single product by ID (returns 200 or 404)
 *   - ?cat={slug_or_id}    : Filter products by category slug (e.g. 'anniversary') or category_id
 *   - ?search={term}       : Search products by cake_name or description
 *   - ?include_all=1       : Include unavailable products (default: active products availability=1)
 *
 * Output:
 *   200 { success: true, count: N, products: [ { id, name, cat, price, badge, img, desc, availability, stock_quantity, categories: [...] } ] }
 *   200 { success: true, product: { ... } } (when single ?id requested)
 *   404 { success: false, message: "Product not found." }
 *   405 { success: false, message: "Method not allowed. Use GET." }
 *   500 { success: false, message: "Failed to load products." }
 */

require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    respond(['success' => false, 'message' => 'Method not allowed. Use GET.'], 405);
}

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

// Parse and sanitize query parameters
$productId   = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : null;
$categoryParam = isset($_GET['cat']) ? strtolower(trim((string) $_GET['cat'])) : '';
$searchTerm  = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$includeAll  = !empty($_GET['include_all']);

try {
    $whereConditions = [];
    $params = [];

    // 1. Availability filter (active only unless include_all requested)
    if (!$includeAll) {
        $whereConditions[] = 'c.availability = 1';
    }

    // 2. Single product filter (?id=N)
    if ($productId !== null) {
        $whereConditions[] = 'c.cake_id = :product_id';
        $params[':product_id'] = $productId;
    }

    // 3. Category filter (?cat=anniversary or ?cat=1)
    if ($categoryParam !== '') {
        $targetCatId = null;
        if (is_numeric($categoryParam)) {
            $targetCatId = (int) $categoryParam;
        } elseif (isset($slugToId[$categoryParam])) {
            $targetCatId = $slugToId[$categoryParam];
        }

        if ($targetCatId !== null) {
            $whereConditions[] = 'c.cake_id IN (SELECT cake_id FROM `cake_category` WHERE category_id = :target_cat_id)';
            $params[':target_cat_id'] = $targetCatId;
        } else {
            // Unrecognized category parameter returns empty set cleanly
            $whereConditions[] = '1 = 0';
        }
    }

    // 4. Search query filter (?search=keyword)
    if ($searchTerm !== '') {
        $whereConditions[] = '(c.cake_name LIKE :search_name OR c.description LIKE :search_desc)';
        $params[':search_name'] = '%' . $searchTerm . '%';
        $params[':search_desc'] = '%' . $searchTerm . '%';
    }

    $whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

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
            GROUP_CONCAT(cat.category_id ORDER BY cat.category_id SEPARATOR ',') AS category_ids,
            GROUP_CONCAT(cat.category_name ORDER BY cat.category_id SEPARATOR '||') AS category_names
        FROM `cake` c
        LEFT JOIN `cake_category` cc ON c.cake_id = cc.cake_id
        LEFT JOIN `category` cat ON cc.category_id = cat.category_id
        {$whereSql}
        GROUP BY c.cake_id
        ORDER BY c.cake_id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format products for complete frontend and API compatibility
    $products = [];
    foreach ($rows as $r) {
        $catIds   = !empty($r['category_ids']) ? array_map('intval', explode(',', $r['category_ids'])) : [];
        $catNames = !empty($r['category_names']) ? explode('||', $r['category_names']) : [];

        $categories = [];
        $slugs = [];

        foreach ($catIds as $idx => $cid) {
            $cname = $catNames[$idx] ?? '';
            $slug  = $categorySlugs[$cid] ?? strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '', $cname)));
            $categories[] = [
                'id'   => $cid,
                'name' => $cname,
                'slug' => $slug,
            ];
            $slugs[] = $slug;
        }

        // Space-separated slug string matching js/site.js expected filter format
        $catString = implode(' ', array_unique($slugs));

        $products[] = [
            'id'             => (int) $r['cake_id'],
            'name'           => (string) $r['cake_name'],
            'cat'            => $catString,
            'price'          => (float) $r['price'],
            'badge'          => (string) ($r['badge'] ?? ''),
            'img'            => (string) ($r['image'] ?? ''),
            'desc'           => (string) ($r['description'] ?? ''),
            'availability'   => (int) $r['availability'],
            'stock_quantity' => isset($r['stock_quantity']) ? (int) $r['stock_quantity'] : null,
            'categories'     => $categories,
        ];
    }

    // Single product response format
    if ($productId !== null) {
        if (empty($products)) {
            respond(['success' => false, 'message' => 'Product not found.'], 404);
        }
        respond([
            'success' => true,
            'product' => $products[0],
        ]);
    }

    // Standard catalog list response format
    respond([
        'success'  => true,
        'count'    => count($products),
        'products' => $products,
    ]);

} catch (PDOException $e) {
    error_log('[KukiCakes products API] DB error: ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Failed to load products from database.'], 500);
}
