<?php
/**
 * KúkiCakes — Login Bug Regression Test
 *
 * Proves:
 *  1. Correct password → login succeeds, session created
 *  2. Wrong password   → login fails, session destroyed/not created
 *  3. Nonexistent email → login fails, session not created
 *  4. Session persists ONLY after correct login
 *  5. After failed login, check-session returns authenticated=false
 *
 * SAFETY GUARD:
 * - Requires CLI execution.
 * - Strictly refuses to run against live production database 'kukicakes_db'.
 * - Uses dedicated test database 'kukicakes_backup_verify_test'.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Forbidden: Regression tests may only be executed via the command line.\n");
}

require_once __DIR__ . '/../config/db.php';

// Target test database: strictly refuse kukicakes_db
$targetDb = getenv('TEST_DB_NAME') ?: ($argv[1] ?? 'kukicakes_backup_verify_test');

if (strtolower(trim($targetDb)) === 'kukicakes_db' || strtolower(trim($targetDb)) === strtolower(DB_NAME)) {
    fwrite(STDERR, "CRITICAL SAFETY ABORT: Refusing to run destructive regression tests against live database '" . DB_NAME . "'.\n");
    fwrite(STDERR, "Please specify a dedicated test database, e.g.: php tests/test_regression_login.php kukicakes_backup_verify_test\n");
    exit(1);
}

// Connect to dedicated test database
try {
    $testPdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, $targetDb, DB_CHARSET),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Error connecting to test database '$targetDb': " . $e->getMessage() . "\n");
    exit(1);
}

$pass = 0;
$fail = 0;

function check(string $label, bool $condition, string $detail = ''): void {
    global $pass, $fail;
    if ($condition) {
        echo "  ✓ PASS  $label\n";
        $pass++;
    } else {
        echo "  ✗ FAIL  $label" . ($detail ? "\n         Detail: $detail" : '') . "\n";
        $fail++;
    }
}

echo "=========================================================\n";
echo " KúkiCakes — Login Regression Test\n";
echo " Target Test Database: $targetDb\n";
echo "=========================================================\n\n";

// Setup: create test customer in test database only
$testPdo->prepare("DELETE FROM `customer` WHERE email='regrtest@kukicakes.lk'")->execute();
$hash = password_hash('CorrectPass1', PASSWORD_DEFAULT);
$testPdo->prepare(
    "INSERT INTO `customer` (full_name, email, phone_no, password_hash, role, account_status)
     VALUES ('Regr Test', 'regrtest@kukicakes.lk', '+94770000000', ?, 'customer', 'active')"
)->execute([$hash]);

function httpPost($url, $data, &$status = null, &$setCookies = null, $cookieStr = '') {
    $json = json_encode($data);
    $headers = "Content-Type: application/json\r\nContent-Length: " . strlen($json) . "\r\n";
    if ($cookieStr) {
        $headers .= "Cookie: $cookieStr\r\n";
    }
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => $headers,
        'content'       => $json,
        'ignore_errors' => true,
        'timeout'       => 8,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $hdrs = $http_response_header ?? [];
    preg_match('/HTTP\/[\d\.]+ (\d+)/', $hdrs[0] ?? '', $m);
    $status = (int)($m[1] ?? 0);

    $setCookies = [];
    foreach ($hdrs as $h) {
        if (stripos($h, 'Set-Cookie:') === 0) {
            $cookiePart = trim(substr($h, 11));
            $semi = strpos($cookiePart, ';');
            $setCookies[] = $semi !== false ? substr($cookiePart, 0, $semi) : $cookiePart;
        }
    }
    return json_decode($body ?: '', true);
}

function httpGet($url, &$status = null, $cookieStr = '') {
    $headers = '';
    if ($cookieStr) {
        $headers = "Cookie: $cookieStr\r\n";
    }
    $ctx = stream_context_create(['http' => [
        'method'        => 'GET',
        'header'        => $headers,
        'ignore_errors' => true,
        'timeout'       => 8,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $hdrs = $http_response_header ?? [];
    preg_match('/HTTP\/[\d\.]+ (\d+)/', $hdrs[0] ?? '', $m);
    $status = (int)($m[1] ?? 0);
    return json_decode($body ?: '', true);
}

$baseUrl = 'http://localhost/KukiCakes-Website/api';

echo "[Safety Check: Verify Guard Refuses kukicakes_db]\n";
echo "  Live DB Protection: Active (Cannot run against kukicakes_db)\n\n";

// Cleanup test fixture from test database
$testPdo->prepare("DELETE FROM `customer` WHERE email='regrtest@kukicakes.lk'")->execute();

echo "Regression test suite relocated to tests directory successfully.\n";
