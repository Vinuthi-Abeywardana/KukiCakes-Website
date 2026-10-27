<?php
/**
 * KúkiCakes — POST /api/update-profile
 *
 * Updates the authenticated user's name and/or phone number.
 * The user_id is taken from the SESSION — never from the request body.
 *
 * Request body (JSON or form-encoded):
 *   full_name, phone
 *
 * Responses:
 *   200 { success: true, user: { ... updated safe fields ... } }
 *   400 { success: false, message: "..." }
 *   401 { success: false, message: "Authentication required." }
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$customerId = requireAuth();

try {
    $stmt = $pdo->prepare(
        'SELECT customer_id, full_name, email, phone_no, account_status, created_at
         FROM `customer` WHERE customer_id = ? LIMIT 1'
    );
    $stmt->execute([$customerId]);
    $current = $stmt->fetch();
    if (!$current) {
        respond(['success' => false, 'message' => 'Customer account not found.'], 404);
    }
} catch (PDOException $e) {
    error_log('[KukiCakes update-profile] DB error: ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Unable to retrieve profile.'], 500);
}

$body   = getBody();
$errors = [];

// 1. Full name validation (min 2, max 100 characters; Unicode letters, spaces, hyphens, apostrophes; no numbers)
$fullName = isset($body['full_name']) ? trim((string) $body['full_name']) : $current['full_name'];
if ($fullName === '') {
    $errors['full_name'] = 'Full name is required.';
} elseif (mb_strlen($fullName) < 2) {
    $errors['full_name'] = 'Full name must be at least 2 characters.';
} elseif (mb_strlen($fullName) > 100) {
    $errors['full_name'] = 'Full name cannot exceed 100 characters.';
} elseif (str_contains($fullName, "\0")) {
    $errors['full_name'] = 'Malformed full name input.';
} elseif (preg_match('/[0-9]/', $fullName)) {
    $errors['full_name'] = 'Full name cannot contain numbers.';
} elseif (!preg_match('/^[\p{L}\p{M}\s\-\'.]+$/u', $fullName)) {
    $errors['full_name'] = 'Full name may only contain letters, spaces, hyphens, and apostrophes.';
} elseif (!preg_match('/\p{L}/u', $fullName)) {
    $errors['full_name'] = 'Full name must contain at least one letter.';
}

// 2. Phone number validation (7 to 20 chars, standard telephony format)
$hasPhoneInBody = array_key_exists('phone', $body);
if ($hasPhoneInBody) {
    $phone = trim((string) $body['phone']);
    if ($phone === '' || $phone === '—' || strtolower($phone) === 'not provided') {
        $phone = '';
    } else {
        if (!preg_match('/^[0-9+\s\-().]{7,20}$/', $phone)) {
            $errors['phone'] = 'Please enter a valid phone number (7 to 20 digits).';
        } elseif (str_contains($phone, "\0")) {
            $errors['phone'] = 'Malformed phone number input.';
        }
    }
} else {
    $phone = (string) ($current['phone_no'] ?? '');
}

// 3. Email address validation (syntax, length, and uniqueness across accounts)
$hasEmailInBody = array_key_exists('email', $body);
if ($hasEmailInBody) {
    $email = strtolower(trim((string) $body['email']));
    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (mb_strlen($email) > 150) {
        $errors['email'] = 'Email address cannot exceed 150 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (str_contains($email, "\0")) {
        $errors['email'] = 'Malformed email input.';
    } elseif ($email !== strtolower((string) $current['email'])) {
        try {
            $chk = $pdo->prepare('SELECT customer_id FROM `customer` WHERE email = ? AND customer_id != ? LIMIT 1');
            $chk->execute([$email, $customerId]);
            if ($chk->fetch()) {
                $errors['email'] = 'This email address is already in use by another account.';
            }
        } catch (PDOException $e) {
            error_log('[KukiCakes update-profile] Email check DB error: ' . $e->getMessage());
            $errors['email'] = 'Unable to verify email availability.';
        }
    }
} else {
    $email = (string) $current['email'];
}

if (!empty($errors)) {
    respond([
        'success' => false,
        'message' => implode(' ', array_values($errors)),
        'errors'  => $errors,
    ], 400);
}

try {
    $stmt = $pdo->prepare(
        'UPDATE `customer` SET full_name = ?, phone_no = ?, email = ?
         WHERE customer_id = ?'
    );
    $stmt->execute([$fullName, $phone, $email, $customerId]);
} catch (PDOException $e) {
    error_log('[KukiCakes update-profile] DB error: ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Update failed. Please try again.'], 500);
}

// Refresh session values
$_SESSION['name']  = $fullName;
$_SESSION['email'] = $email;
$_SESSION['phone'] = $phone;

// Return updated profile
$stmt = $pdo->prepare(
    'SELECT customer_id, full_name, email, phone_no, account_status, created_at
     FROM `customer` WHERE customer_id = ? LIMIT 1'
);
$stmt->execute([$customerId]);
$user = $stmt->fetch();

$joinDate = date('j M Y', strtotime($user['created_at']));

respond([
    'success' => true,
    'user' => [
        'customer_id' => (int) $user['customer_id'],
        'name'        => $user['full_name'],
        'email'       => $user['email'],
        'phone'       => $user['phone_no'],
        'joinDate'    => $joinDate,
        'status'      => $user['account_status'],
    ],
]);
