<?php
/**
 * KúkiCakes — POST /api/login
 *
 * Authenticates an existing user and creates a server-side session.
 *
 * IMPORTANT: Any existing session is destroyed at the start of every
 * login attempt — before credentials are verified. This prevents a
 * previously authenticated session from persisting when the submitted
 * password is wrong.
 *
 * Request body (JSON or form-encoded):
 *   email, password
 *
 * Responses:
 *   200 { success: true, user: { customer_id, name, email, phone, joinDate } }
 *   401 { success: false, message: "Invalid email or password." }   (generic — no enumeration)
 *   500 { success: false, message: "..." }
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed.'], 405);
}

// ── Destroy any existing session BEFORE validating credentials ─
// This guarantees that any previous session is completely invalidated.
// If the user submits wrong credentials, they will NOT retain any
// active session or authenticated state.
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

$body     = getBody();
$rawIdent = trim((string) ($body['email'] ?? $body['username'] ?? ''));
$email    = strtolower($rawIdent);
$password = (string) ($body['password'] ?? '');

if ($rawIdent === '' || $password === '') {
    respond(['success' => false, 'message' => 'Invalid email or password.'], 401);
}

// ── 1. Look up in `customer` table by email ───────────────────
$user = null;
try {
    $stmt = $pdo->prepare(
        'SELECT customer_id, full_name, email, phone_no, password_hash, role,
                account_status, created_at
         FROM `customer`
         WHERE email = ?
         LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[KukiCakes login] Customer lookup DB error: ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Invalid email or password.'], 401);
}

// If customer account matched:
if ($user) {
    if (empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        respond(['success' => false, 'message' => 'Invalid email or password.'], 401);
    }

    if ($user['account_status'] !== 'active') {
        respond(['success' => false, 'message' => 'Invalid email or password.'], 401);
    }

    // Credentials verified — start a fresh authenticated customer session
    session_name('KUKI_SESS');
    session_start();
    session_regenerate_id(true);

    $_SESSION['customer_id'] = (int) $user['customer_id'];
    $_SESSION['email']       = $user['email'];
    $_SESSION['name']        = $user['full_name'];
    $_SESSION['phone']       = (string) ($user['phone_no'] ?? '');
    $_SESSION['role']        = (string) ($user['role'] ?? 'customer');

    if (empty($_SESSION['customer_id'])) {
        error_log('[KukiCakes login] Session write failed for customer_id=' . $user['customer_id']);
        respond(['success' => false, 'message' => 'Login failed. Please try again.'], 500);
    }

    $joinDate = date('j M Y', strtotime($user['created_at']));
    respond([
        'success' => true,
        'user' => [
            'customer_id' => (int) $user['customer_id'],
            'name'        => $user['full_name'],
            'email'       => $user['email'],
            'phone'       => $user['phone_no'],
            'role'        => (string) ($user['role'] ?? 'customer'),
            'joinDate'    => $joinDate,
        ],
    ]);
}

// ── 2. No customer match: Look up in `administrator` table ─────
// Check by email or username
$admin = null;
try {
    $adminStmt = $pdo->prepare(
        'SELECT admin_id, username, password, email, created_at
         FROM `administrator`
         WHERE email = ? OR username = ?
         LIMIT 1'
    );
    $adminStmt->execute([$email, $rawIdent]);
    $admin = $adminStmt->fetch();
} catch (PDOException $e) {
    error_log('[KukiCakes login] Administrator lookup DB error: ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Invalid email or password.'], 401);
}

if (!$admin || empty($admin['password'])) {
    // Perform dummy verify to prevent timing attacks
    password_verify($password, '$2y$10$invalidhashpaddinginvalidinvalidinvalidinvalidinvalid00');
    respond(['success' => false, 'message' => 'Invalid email or password.'], 401);
}

if (!password_verify($password, $admin['password'])) {
    respond(['success' => false, 'message' => 'Invalid email or password.'], 401);
}

// Credentials verified — start a fresh authenticated administrator session
session_name('KUKI_SESS');
session_start();
session_regenerate_id(true);

// STRICT ACCOUNT SEPARATION:
// Set $_SESSION['admin_id'] and $_SESSION['role'] = 'admin'.
// NEVER set $_SESSION['customer_id'] for an administrator.
$_SESSION['admin_id'] = (int) $admin['admin_id'];
$_SESSION['role']     = 'admin';
$_SESSION['name']     = $admin['username'];
$_SESSION['email']    = $admin['email'];

if (empty($_SESSION['admin_id'])) {
    error_log('[KukiCakes login] Session write failed for admin_id=' . $admin['admin_id']);
    respond(['success' => false, 'message' => 'Login failed. Please try again.'], 500);
}

respond([
    'success' => true,
    'user' => [
        'admin_id' => (int) $admin['admin_id'],
        'name'     => $admin['username'],
        'email'    => $admin['email'],
        'role'     => 'admin',
        'is_admin' => true,
    ],
]);
