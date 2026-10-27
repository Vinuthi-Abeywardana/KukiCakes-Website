<?php
/**
 * KúkiCakes — CLI-Only Test Reset Token Generator
 *
 * For local testing and automated test suites when SMTP/mail delivery
 * is not configured. Strictly forbidden via HTTP / web server.
 *
 * Usage:
 *   php tests/generate_test_reset_token.php <email>
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Forbidden: This utility can only be executed via the command line.'], JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$email = strtolower(trim($argv[1] ?? ''));
if (!$email) {
    echo json_encode(['success' => false, 'error' => 'Email argument required. Usage: php generate_test_reset_token.php <email>']);
    exit(1);
}

$stmt = $pdo->prepare('SELECT customer_id, full_name, email, account_status FROM `customer` WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || $user['account_status'] !== 'active') {
    echo json_encode(['success' => false, 'error' => 'Customer account not found or inactive']);
    exit(1);
}

$plainToken = bin2hex(random_bytes(32));
$tokenHash  = hash('sha256', $plainToken);

// Invalidate previous tokens
$stmt = $pdo->prepare('UPDATE `password_resets` SET used_at = NOW() WHERE customer_id = ? AND used_at IS NULL');
$stmt->execute([(int) $user['customer_id']]);

// Insert new token
$stmt = $pdo->prepare('INSERT INTO `password_resets` (customer_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))');
$stmt->execute([(int) $user['customer_id'], $tokenHash]);

echo json_encode([
    'success'     => true,
    'customer_id' => (int) $user['customer_id'],
    'email'       => $user['email'],
    'reset_token' => $plainToken,
    'reset_url'   => '#reset-password?token=' . $plainToken,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
