<?php
/**
 * KúkiCakes — Database Migration for Week 8 Category Mappings (Phase 2B)
 *
 * Populates the `cake_category` junction table based on authoritative
 * category metadata in `js/data.js` and existing database tables.
 *
 * Safety & Pre-Flight Validations:
 * 1. Target database is verified to be `kukicakes_db`.
 * 2. All 58 catalog items are parsed from `js/data.js` and verified
 *    character-for-character against `cake` table records.
 * 3. Every category slug is verified against both ID and exact name in `category`.
 * 4. Exactly 70 unique (cake_id, category_id) pairs are generated.
 * 5. Category distribution strictly matches approved audit:
 *    Anniversary: 7, Birthday: 15, Christmas: 4, Cupcake: 8, Engagement: 5,
 *    Graduation: 5, Number Cakes: 6, Party: 15, Wedding: 5.
 * 6. All foreign-key references are validated before any write occurs.
 * 7. Operations are wrapped in a PDO transaction (atomic commit or complete rollback).
 * 8. Idempotent via ON DUPLICATE KEY UPDATE (safe to run repeatedly).
 * 9. Leaves all other tables (`cake`, `category`, `customer`, `orders`, etc.) untouched.
 */

// Application timezone
date_default_timezone_set('Asia/Colombo');

// Plain text output for CLI / web output
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/../config/db.php';

echo "KúkiCakes — Week 8 Category Mapping Migration\n";
echo str_repeat('=', 55) . "\n";

try {
    // ---------------------------------------------------------------
    // 1. Validate Active Database
    // ---------------------------------------------------------------
    $activeDb = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($activeDb !== 'kukicakes_db') {
        throw new RuntimeException("Validation failed: Connected to unexpected database '{$activeDb}'. Expected 'kukicakes_db'.");
    }
    echo "✓ Target database confirmed: kukicakes_db\n";

    // ---------------------------------------------------------------
    // 2. Read and Parse js/data.js (Handling Escaped Apostrophes & Quotes)
    // ---------------------------------------------------------------
    $jsPath = __DIR__ . '/../js/data.js';
    if (!file_exists($jsPath)) {
        throw new RuntimeException("Validation failed: Catalog source file not found at {$jsPath}.");
    }

    $jsContent = file_get_contents($jsPath);
    if (!preg_match('/const catalogProducts = \[(.*?)\];\s*const catalogGallery/s', $jsContent, $matches)) {
        throw new RuntimeException("Validation failed: Unable to locate catalogProducts in {$jsPath}.");
    }

    // Robust regex matching single-quoted string literals handling escaped characters (e.g. \' and \\)
    $pattern = '/\{\s*id:\s*(\d+),\s*name:\s*\'((?:\\\\.|\'\'|[^\'])*)\',\s*cat:\s*\'((?:\\\\.|\'\'|[^\'])*)\'/s';
    preg_match_all($pattern, $matches[1], $items, PREG_SET_ORDER);

    if (count($items) !== 58) {
        throw new RuntimeException("Validation failed: Expected 58 catalog products, found " . count($items) . ".");
    }
    echo "✓ Catalog source parsed: Exactly 58 products extracted\n";

    // ---------------------------------------------------------------
    // 3. Character-for-Character Validation Against Database Cakes
    // ---------------------------------------------------------------
    $dbCakes = $pdo->query('SELECT cake_id, cake_name FROM `cake` ORDER BY cake_id ASC')->fetchAll(PDO::FETCH_ASSOC);
    if (count($dbCakes) !== 58) {
        throw new RuntimeException("Validation failed: Expected 58 cakes in database, found " . count($dbCakes) . ".");
    }

    $dbCakeMap = [];
    foreach ($dbCakes as $c) {
        $dbCakeMap[(int)$c['cake_id']] = trim($c['cake_name']);
    }

    $catalogProducts = [];
    foreach ($items as $item) {
        $id = (int)$item[1];
        // Decode JavaScript string escapes: \' -> ', \\ -> \
        $rawEscapedName = $item[2];
        $decodedName = str_replace(["\\'", "\\\\"], ["'", "\\"], $rawEscapedName);
        $decodedName = trim($decodedName);
        $cat = trim($item[3]);

        if (!isset($dbCakeMap[$id])) {
            throw new RuntimeException("Validation failed: Catalog cake ID {$id} not found in database.");
        }

        // Strict character-for-character check
        if ($dbCakeMap[$id] !== $decodedName) {
            throw new RuntimeException("Validation failed: Exact name mismatch on ID {$id}.\n  Extracted: '{$decodedName}'\n  Database:  '{$dbCakeMap[$id]}'");
        }

        $catalogProducts[] = [
            'id'   => $id,
            'name' => $decodedName,
            'cat'  => $cat,
        ];
    }
    echo "✓ Product validation: All 58 product IDs and names match database records character-for-character\n";

    // ---------------------------------------------------------------
    // 4. Strict Category ID and Name Validation
    // ---------------------------------------------------------------
    $dbCats = $pdo->query('SELECT category_id, category_name FROM `category` ORDER BY category_id ASC')->fetchAll(PDO::FETCH_ASSOC);
    $categoryDbMap = [];
    foreach ($dbCats as $cat) {
        $categoryDbMap[(int)$cat['category_id']] = $cat['category_name'];
    }

    // Required authoritative mapping contract:
    // slug -> [id, name]
    $authoritativeCategories = [
        'anniversary' => ['id' => 1, 'name' => 'Anniversary'],
        'birthday'    => ['id' => 2, 'name' => 'Birthday'],
        'christmas'   => ['id' => 3, 'name' => 'Christmas'],
        'cupcake'     => ['id' => 4, 'name' => 'Cupcake'],
        'engagement'  => ['id' => 5, 'name' => 'Engagement'],
        'graduation'  => ['id' => 6, 'name' => 'Graduation'],
        'number'      => ['id' => 7, 'name' => 'Number Cakes'],
        'party'       => ['id' => 8, 'name' => 'Party'],
        'wedding'     => ['id' => 9, 'name' => 'Wedding'],
    ];

    foreach ($authoritativeCategories as $slug => $expected) {
        $expectedId   = $expected['id'];
        $expectedName = $expected['name'];

        if (!isset($categoryDbMap[$expectedId])) {
            throw new RuntimeException("Validation failed: Category ID {$expectedId} for '{$slug}' does not exist in database.");
        }
        if ($categoryDbMap[$expectedId] !== $expectedName) {
            throw new RuntimeException("Validation failed: Category ID {$expectedId} name mismatch. Expected '{$expectedName}', found '{$categoryDbMap[$expectedId]}'.");
        }
    }
    echo "✓ Category contract validated: All 9 category IDs and names match database records exactly\n";

    // ---------------------------------------------------------------
    // 5. Build and Validate Unique (cake_id, category_id) Pairs
    // ---------------------------------------------------------------
    $pairs = [];
    $pairKeys = [];

    foreach ($catalogProducts as $p) {
        $tokens = preg_split('/\s+/', trim($p['cat']));
        $cakeCatIds = [];

        foreach ($tokens as $token) {
            $slug = strtolower(trim($token));
            if (!isset($authoritativeCategories[$slug])) {
                throw new RuntimeException("Validation failed: Unknown category slug '{$slug}' in cake ID {$p['id']} ('{$p['name']}').");
            }
            $catId = $authoritativeCategories[$slug]['id'];
            $cakeCatIds[$catId] = true;
        }

        foreach (array_keys($cakeCatIds) as $catId) {
            $key = "{$p['id']}-{$catId}";
            if (isset($pairKeys[$key])) {
                throw new RuntimeException("Validation failed: Duplicate mapping detected for cake ID {$p['id']} and category ID {$catId}.");
            }
            $pairKeys[$key] = true;
            $pairs[] = [
                'cake_id'       => $p['id'],
                'category_id'   => $catId,
                'cake_name'     => $p['name'],
                'category_name' => $authoritativeCategories[array_search($catId, array_column($authoritativeCategories, 'id'))]['name'] ?? $categoryDbMap[$catId],
            ];
        }
    }

    if (count($pairs) !== 70) {
        throw new RuntimeException("Validation failed: Generated " . count($pairs) . " mapping pairs. Expected exactly 70.");
    }
    echo "✓ Mapping pairs validated: Exactly 70 unique (cake_id, category_id) pairs generated\n";

    // ---------------------------------------------------------------
    // 6. Validate Category Distribution Against Approved Audit
    // ---------------------------------------------------------------
    $expectedDistribution = [
        'Anniversary'  => 7,
        'Birthday'     => 15,
        'Christmas'    => 4,
        'Cupcake'      => 8,
        'Engagement'   => 5,
        'Graduation'   => 5,
        'Number Cakes' => 6,
        'Party'        => 15,
        'Wedding'      => 5,
    ];

    $actualDistribution = [];
    foreach ($pairs as $pair) {
        $catName = $pair['category_name'];
        $actualDistribution[$catName] = ($actualDistribution[$catName] ?? 0) + 1;
    }
    ksort($actualDistribution);
    ksort($expectedDistribution);

    if ($actualDistribution !== $expectedDistribution) {
        throw new RuntimeException("Validation failed: Category distribution mismatch against approved audit.");
    }
    echo "✓ Category distribution validated against approved audit:\n";
    foreach ($actualDistribution as $catName => $count) {
        printf("    - %-14s: %2d cakes\n", $catName, $count);
    }

    // ---------------------------------------------------------------
    // 7. Atomic Transactional Insert (Idempotent via ON DUPLICATE KEY)
    // ---------------------------------------------------------------
    echo "\nExecuting database transaction...\n";
    $pdo->beginTransaction();

    $insertStmt = $pdo->prepare('
        INSERT INTO `cake_category` (cake_id, category_id)
        VALUES (:cake_id, :category_id)
        ON DUPLICATE KEY UPDATE cake_id = VALUES(cake_id)
    ');

    $insertedCount = 0;
    foreach ($pairs as $p) {
        $insertStmt->execute([
            ':cake_id'     => $p['cake_id'],
            ':category_id' => $p['category_id'],
        ]);
        $insertedCount++;
    }

    $pdo->commit();
    echo "✓ Transaction committed successfully: {$insertedCount} records processed\n";

    // ---------------------------------------------------------------
    // 8. Post-Migration Verification Queries & Breakdown Reporting
    // ---------------------------------------------------------------
    $totalMappings = (int) $pdo->query('SELECT COUNT(*) FROM `cake_category`')->fetchColumn();
    $distinctCakes = (int) $pdo->query('SELECT COUNT(DISTINCT cake_id) FROM `cake_category`')->fetchColumn();
    $distinctCats  = (int) $pdo->query('SELECT COUNT(DISTINCT category_id) FROM `cake_category`')->fetchColumn();

    echo "\n=== POST-MIGRATION VERIFICATION ===\n";
    echo "  Total mapping rows in cake_category : {$totalMappings} (Expected: 70)\n";
    echo "  Distinct cakes mapped               : {$distinctCakes} (Expected: 58)\n";
    echo "  Distinct categories mapped          : {$distinctCats} (Expected: 9)\n\n";

    echo "Category Breakdown in `cake_category`:\n";
    $breakdownStmt = $pdo->query('
        SELECT c.category_id, c.category_name, COUNT(cc.cake_id) AS cake_count
        FROM `category` c
        JOIN `cake_category` cc ON c.category_id = cc.category_id
        GROUP BY c.category_id, c.category_name
        ORDER BY c.category_id ASC
    ');
    $dbBreakdown = $breakdownStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($dbBreakdown as $row) {
        $expCount = $expectedDistribution[$row['category_name']] ?? 0;
        $status = ((int)$row['cake_count'] === $expCount) ? '✓ OK' : '✗ MISMATCH';
        printf("  [ID %2d] %-14s: %2d cakes (Expected: %2d) [%s]\n",
            $row['category_id'], $row['category_name'], $row['cake_count'], $expCount, $status);
    }

    if ($totalMappings !== 70 || $distinctCakes !== 58 || $distinctCats !== 9) {
        throw new RuntimeException("Post-migration verification failed: row counts do not match expected 70/58/9.");
    }

    echo "\n✓ Phase 2B category mapping migration completed and verified successfully.\n";

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        echo "✗ Database transaction rolled back.\n";
    }
    echo "\n[ERROR] Migration aborted: " . $e->getMessage() . "\n";
    exit(1);
}
