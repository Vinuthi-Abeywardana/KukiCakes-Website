<?php
/**
 * KúkiCakes — Shared API Bootstrap (api/bootstrap.php)
 *
 * Included at the top of every API endpoint.
 * Sets JSON headers, starts a secure session, and provides helpers.
 */

// ── Application timezone ───────────────────────────────────────
date_default_timezone_set('Asia/Colombo');

// ── JSON + security headers ────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);      // No JS access to cookie
    ini_set('session.use_strict_mode', 1);      // Reject unknown session IDs
    ini_set('session.cookie_samesite', 'Lax');  // CSRF mitigation (Lax works with local HTTP)
    session_name('KUKI_SESS');
    session_start();
}

// ── DB connection ──────────────────────────────────────────────
require_once __DIR__ . '/../config/db.php';

// ── Helpers ────────────────────────────────────────────────────

/**
 * Send a JSON response and exit.
 */
function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Check if current session has an authenticated customer.
 */
function isAuthenticated(): bool {
    return !empty($_SESSION['customer_id']) && (($_SESSION['role'] ?? 'customer') === 'customer');
}

/**
 * Return the authenticated customer_id from the session,
 * or respond with 401/403 and exit if not authenticated as a customer.
 */
function requireAuth(): int {
    if (empty($_SESSION['customer_id'])) {
        if (!empty($_SESSION['admin_id']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
            respond(['success' => false, 'message' => 'Access denied. Customer account required.'], 403);
        }
        respond(['success' => false, 'message' => 'Authentication required.'], 401);
    }
    if (isset($_SESSION['role']) && $_SESSION['role'] !== 'customer') {
        respond(['success' => false, 'message' => 'Access denied. Customer account required.'], 403);
    }
    return (int) $_SESSION['customer_id'];
}

/**
 * Check if current session has an authenticated administrator.
 */
function isAdmin(): bool {
    return !empty($_SESSION['admin_id']) && (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}

/**
 * Enforce administrator access control.
 * Responds with 401 if unauthenticated, 403 if authenticated but not an admin.
 * Returns the verified administrator ID.
 */
function requireAdmin(): int {
    if (empty($_SESSION['admin_id']) && empty($_SESSION['customer_id'])) {
        respond(['success' => false, 'message' => 'Authentication required.'], 401);
    }
    if (empty($_SESSION['admin_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        respond(['success' => false, 'message' => 'Access denied. Administrator privileges required.'], 403);
    }
    return (int) $_SESSION['admin_id'];
}

/**
 * Decode JSON request body (for POST/PUT with JSON content-type).
 * Falls back to $_POST for form-encoded requests.
 */
function getBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return $decoded;
    }
    return $_POST;
}
