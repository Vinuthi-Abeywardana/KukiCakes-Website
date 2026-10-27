<?php
/**
 * KúkiCakes — GET /api/check-session
 *
 * Lightweight endpoint used by the frontend on page load to
 * check whether the user has a valid authenticated session,
 * without needing to call the heavier /api/profile.
 *
 * Responses:
 *   200 { authenticated: true,  user: { customer_id, name, email } }
 *   200 { authenticated: false }
 */
require_once __DIR__ . '/bootstrap.php';

if (isAdmin()) {
    respond([
        'authenticated' => true,
        'user' => [
            'admin_id' => (int) $_SESSION['admin_id'],
            'name'     => (string) ($_SESSION['name'] ?? ''),
            'email'    => (string) ($_SESSION['email'] ?? ''),
            'role'     => 'admin',
            'is_admin' => true,
        ],
    ]);
}

if (!empty($_SESSION['customer_id']) && (($_SESSION['role'] ?? 'customer') === 'customer')) {
    $phone = $_SESSION['phone'] ?? '';
    if ($phone === '') {
        try {
            $stmt = $pdo->prepare('SELECT phone_no FROM `customer` WHERE customer_id = ? LIMIT 1');
            $stmt->execute([(int) $_SESSION['customer_id']]);
            $phone = (string) ($stmt->fetchColumn() ?: '');
            $_SESSION['phone'] = $phone;
        } catch (PDOException $e) {
            $phone = '';
        }
    }
    respond([
        'authenticated' => true,
        'user' => [
            'customer_id' => (int) $_SESSION['customer_id'],
            'name'        => (string) ($_SESSION['name']  ?? ''),
            'email'       => (string) ($_SESSION['email'] ?? ''),
            'phone'       => $phone,
            'role'        => (string) ($_SESSION['role'] ?? 'customer'),
            'is_admin'    => false,
        ],
    ]);
}

respond(['authenticated' => false]);
