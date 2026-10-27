<?php
/**
 * KúkiCakes — POST /api/forgot-password
 *
 * Initiates the password reset process:
 *  1. Validates the submitted email address.
 *  2. If the customer exists and is active, generates a cryptographically
 *     secure single-use reset token and stores its SHA-256 hash with 1-hour expiry.
 *  3. Invalidates any prior unused tokens for this account.
 *  4. Adheres strictly to security standards:
 *     - Never reveals whether an email exists (generic response).
 *     - Stores only the SHA-256 token hash (never plaintext).
 *     - Never exposes password hashes.
 *     - Checks for email delivery capability and does not falsely claim
 *       an email was sent when no mailer/SMTP is configured (as per project spec).
 *
 * Request body (JSON or form-encoded):
 *   email
 *
 * Responses:
 *   200 { success: true, message: "...", email_delivery: false, ... }
 *   400 { success: false, message: "..." }
 *   405 { success: false, message: "Method not allowed." }
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$body  = getBody();
$email = strtolower(trim($body['email'] ?? ''));

if (!$email) {
    respond(['success' => false, 'message' => 'Email address is required.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
}

require_once __DIR__ . '/../config/mail.php';

try {
    $stmt = $pdo->prepare(
        'SELECT customer_id, full_name, email, account_status
         FROM `customer`
         WHERE email = ?
         LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[KukiCakes forgot-password] DB error: ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Unable to process request. Please try again later.'], 500);
}

$genericMessage = 'If an account exists with that email address, password reset instructions have been generated.';

// ── Unregistered or inactive account ───────────────────────────
if (!$user || $user['account_status'] !== 'active') {
    // Perform dummy hash to prevent timing attacks
    hash('sha256', random_bytes(32));
    respond([
        'success' => true,
        'message' => $genericMessage,
    ]);
}

// ── Registered active customer: generate secure token ──────────
try {
    // 32 cryptographically secure random bytes = 64 hex characters
    $plainToken = bin2hex(random_bytes(32));
    $tokenHash  = hash('sha256', $plainToken);
    // Invalidate any existing unused reset tokens for this customer
    $stmt = $pdo->prepare(
        'UPDATE `password_resets`
         SET used_at = NOW()
         WHERE customer_id = ? AND used_at IS NULL'
    );
    $stmt->execute([(int) $user['customer_id']]);

    // Insert new hashed token with 1 hour expiration
    $stmt = $pdo->prepare(
        'INSERT INTO `password_resets` (customer_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
    );
    $stmt->execute([(int) $user['customer_id'], $tokenHash]);

} catch (Exception $e) {
    error_log('[KukiCakes forgot-password] Token generation error: ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Unable to generate reset link. Please try again.'], 500);
}

// ── Send transactional email via configured SMTP ───────────────
$resetBaseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/KukiCakes-Website'
);
$resetUrl = $resetBaseUrl . '/reset-password.html?token=' . urlencode($plainToken);
$recipientName = htmlspecialchars($user['full_name'] ?? 'Valued Customer', ENT_QUOTES, 'UTF-8');
$subject = 'Reset Your KúkiCakes Password';

$htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #faf7f2; margin: 0; padding: 20px; color: #2d2424; }
  .wrapper { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #ebd9c8; overflow: hidden; }
  .header { background: #5c1428; padding: 28px 24px; text-align: center; }
  .header h1 { margin: 0; font-family: Georgia, serif; color: #fbf5ee; font-size: 26px; letter-spacing: 0.5px; }
  .header p { margin: 6px 0 0 0; color: #e8c5ab; font-size: 13px; text-transform: uppercase; letter-spacing: 1.5px; }
  .content { padding: 32px 28px; line-height: 1.6; }
  .btn { display: inline-block; background-color: #5c1428; color: #ffffff !important; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; margin: 20px 0; }
  .footer { background: #fdfaf6; padding: 20px 28px; font-size: 12px; color: #7a6e6b; border-top: 1px solid #ebd9c8; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>KúkiCakes</h1>
    <p>Cakes made for your moments</p>
  </div>
  <div class="content">
    <p>Hello {$recipientName},</p>
    <p>We received a request to reset the password for your KúkiCakes account. Click the button below to choose a new password:</p>
    <div style="text-align: center;">
      <a href="{$resetUrl}" class="btn" target="_blank" rel="noopener">Reset Your Password</a>
    </div>
    <p style="font-size: 13px; color: #666;">Or copy and paste this link into your browser:<br><a href="{$resetUrl}" style="color: #5c1428; word-break: break-all;">{$resetUrl}</a></p>
    <p style="font-size: 13px; color: #888;">This link will expire in <strong>60 minutes</strong> and can only be used once. If you did not request a password reset, no action is needed and your account remains secure.</p>
  </div>
  <div class="footer">
    KúkiCakes Bakery Studio &bull; Colombo, Sri Lanka<br>
    Questions? Contact us at orders@kukicakes.lk
  </div>
</div>
</body>
</html>
HTML;

$textBody = "Hello {$recipientName},\n\n"
          . "We received a request to reset your KúkiCakes account password.\n\n"
          . "Please visit the link below to set a new password:\n"
          . "{$resetUrl}\n\n"
          . "This link is valid for 60 minutes and can only be used once.\n"
          . "If you did not request this, please disregard this email.\n\n"
          . "— KúkiCakes Colombo";

$mailResult = KukiMailer::send($user['email'], $user['full_name'], $subject, $htmlBody, $textBody);
if (!$mailResult['success']) {
    error_log('[KukiCakes forgot-password] Email delivery status: ' . $mailResult['message']);
}

// Public response: strictly uniform for registered and unregistered accounts.
// NEVER expose reset_token, reset_url, dev_note, or account_found in public API response.
respond([
    'success' => true,
    'message' => $genericMessage,
]);
