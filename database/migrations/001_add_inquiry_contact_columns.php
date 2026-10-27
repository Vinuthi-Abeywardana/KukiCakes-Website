<?php
/**
 * KúkiCakes — Database Migration: Add Contact Columns to Inquiry Table
 *
 * Migration: 001_add_inquiry_contact_columns.php
 * Date: 2026-10-18
 *
 * Adds three nullable contact fields to the existing `inquiry` table:
 * - `contact_name`  VARCHAR(100) NULL DEFAULT NULL
 * - `contact_phone` VARCHAR(20)  NULL DEFAULT NULL
 * - `contact_email` VARCHAR(150) NULL DEFAULT NULL
 *
 * Safety & Idempotency:
 * - Verifies target database is 'kukicakes_db' before making changes.
 * - Inspects INFORMATION_SCHEMA.COLUMNS for each column before applying ALTER TABLE.
 * - Safe to re-run multiple times without duplicate column errors.
 * - Preserves all existing tables, foreign keys, constraints, and rows.
 * - Never drops or recreates the database or inquiry table.
 */

date_default_timezone_set('Asia/Colombo');

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Forbidden: Database migrations may only be executed via the command line.\n");
}

require_once __DIR__ . '/../../config/db.php';

$results = [];

// 1. Verify target database
$dbNameStmt = $pdo->query("SELECT DATABASE()");
$currentDb = $dbNameStmt->fetchColumn();

if ($currentDb !== 'kukicakes_db') {
    die("ABORT: Expected database 'kukicakes_db', but connected to '{$currentDb}'.\n");
}
$results[] = "✓ Verified target database: {$currentDb}";

// 2. Define the columns to add
$columnsToAdd = [
    'contact_name' => [
        'def' => "VARCHAR(100) NULL DEFAULT NULL",
        'after' => 'customer_id'
    ],
    'contact_phone' => [
        'def' => "VARCHAR(20) NULL DEFAULT NULL",
        'after' => 'contact_name'
    ],
    'contact_email' => [
        'def' => "VARCHAR(150) NULL DEFAULT NULL",
        'after' => 'contact_phone'
    ],
];

// 3. Inspect and alter idempotently
foreach ($columnsToAdd as $colName => $colInfo) {
    $check = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'inquiry' 
          AND COLUMN_NAME = ?
    ");
    $check->execute([$colName]);
    $exists = ((int) $check->fetchColumn()) > 0;

    if (!$exists) {
        // Check if the AFTER column exists
        $afterCol = $colInfo['after'];
        $checkAfter = $pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'inquiry' 
              AND COLUMN_NAME = ?
        ");
        $checkAfter->execute([$afterCol]);
        $afterExists = ((int) $checkAfter->fetchColumn()) > 0;

        $afterClause = $afterExists ? "AFTER `{$afterCol}`" : "";
        $sql = "ALTER TABLE `inquiry` ADD COLUMN `{$colName}` {$colInfo['def']} {$afterClause}";
        $pdo->exec($sql);
        $results[] = "✓ Added column `inquiry`.`{$colName}` ({$colInfo['def']}).";
    } else {
        $results[] = "→ Column `inquiry`.`{$colName}` already exists, skipped.";
    }
}

// 4. Output summary & verify schema
echo "KúkiCakes — Database Migration: 001_add_inquiry_contact_columns\n";
echo str_repeat('=', 60) . "\n";
foreach ($results as $res) {
    echo $res . "\n";
}

echo "\nCurrent `inquiry` Table Columns:\n";
echo str_repeat('-', 60) . "\n";
$cols = $pdo->query("SHOW FULL COLUMNS FROM `inquiry`")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo sprintf(
        "%-20s %-16s Null:%-4s Default:%-8s\n",
        $c['Field'],
        $c['Type'],
        $c['Null'],
        $c['Default'] ?? 'NULL'
    );
}
