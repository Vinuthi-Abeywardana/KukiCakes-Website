<?php
/**
 * KúkiCakes — Database Migration for Week 8 Inventory (Phase 1)
 *
 * Adds nullable `stock_quantity` column to the `cake` table.
 *
 * Safety & Idempotency:
 * - Checks INFORMATION_SCHEMA before applying ALTER TABLE.
 * - Leaves stock_quantity as NULL for existing cakes (no invented stock quantities).
 * - Leaves existing availability, pricing, images, and descriptions completely untouched.
 * - Does not alter users, orders, order items, or any other tables.
 * - Safe to run multiple times without error or duplication.
 */

// Application timezone
date_default_timezone_set('Asia/Colombo');

// Set plain text output for CLI / web output
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/../config/db.php';

$results = [];

// ---------------------------------------------------------------
// 1. Add `stock_quantity` to `cake` table if it does not exist
// ---------------------------------------------------------------
$check = $pdo->prepare("
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'cake' 
      AND COLUMN_NAME = 'stock_quantity'
");
$check->execute();
$columnExists = ((int) $check->fetchColumn()) > 0;

if (!$columnExists) {
    // Add nullable integer column without arbitrary default stock
    $sql = "ALTER TABLE `cake` ADD COLUMN `stock_quantity` INT(11) DEFAULT NULL AFTER `availability`";
    $pdo->exec($sql);
    $results[] = "✓ Added column `cake`.`stock_quantity` (INT(11) DEFAULT NULL).";
} else {
    $results[] = "→ Column `cake`.`stock_quantity` already exists, skipped.";
}

// ---------------------------------------------------------------
// 2. Summary & Verification Output
// ---------------------------------------------------------------
echo "KúkiCakes — Week 8 Phase 1 Migration\n";
echo str_repeat('=', 45) . "\n";
foreach ($results as $r) {
    echo $r . "\n";
}

// Verify current columns on cake table
echo "\nCurrent `cake` Table Schema Status:\n";
$stmt = $pdo->query("DESCRIBE `cake`");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    printf("  %-18s %-16s %-6s Default: %s\n", 
        $col['Field'], 
        $col['Type'], 
        $col['Null'], 
        $col['Default'] ?? 'NULL'
    );
}

$cakeCount = (int) $pdo->query("SELECT COUNT(*) FROM `cake`")->fetchColumn();
echo "\nTotal Cakes Preserved: {$cakeCount}\n";
echo "Migration check complete.\n";
