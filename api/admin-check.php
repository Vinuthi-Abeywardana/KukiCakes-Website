<?php
/**
 * KúkiCakes — GET /api/admin-check.php
 *
 * Server-side authorization check for administrative access.
 *
 * Responses:
 *   200 { success: true, admin: true, user: { customer_id, name, email, role } }
 *   401 { success: false, message: "Authentication required." }
 *   403 { success: false, message: "Access denied. Administrator privileges required." }
 *   405 { success: false, message: "Method not allowed. Use GET." }
 */
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    respond(['success' => false, 'message' => 'Method not allowed. Use GET.'], 405);
}

// Enforce admin privileges (401 if unauthenticated, 403 if customer)
$adminId = requireAdmin();

respond([
    'success' => true,
    'admin'   => true,
    'user'    => [
        'admin_id' => (int) $adminId,
        'name'     => (string) ($_SESSION['name'] ?? ''),
        'email'    => (string) ($_SESSION['email'] ?? ''),
        'role'     => (string) ($_SESSION['role'] ?? 'admin'),
        'is_admin' => true,
    ],
]);
