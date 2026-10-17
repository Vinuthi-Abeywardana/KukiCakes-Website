<?php
/**
 * KúkiCakes — PayHere Payment Gateway Configuration
 *
 * Secure server-side configuration for PayHere Sandbox integration.
 *
 * IMPORTANT SECURITY RULES:
 * 1. This file resides server-side and must NEVER be exposed to clients.
 * 2. The PAYHERE_MERCHANT_SECRET must NEVER be sent in JSON, HTML, or JavaScript.
 * 3. Replace the placeholder values with your real Sandbox credentials from the PayHere Dashboard.
 */

// Prevent direct execution via web request
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'payhere.php') {
    http_response_code(403);
    exit('Access forbidden.');
}

// Check for optional local environment override file (git-ignored, never committed)
$localConfig = __DIR__ . '/payhere.local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
}

// ---------------------------------------------------------------
// PayHere Sandbox Credentials
// (Can be overridden via environment variables if desired)
// ---------------------------------------------------------------

// PayHere Sandbox Merchant ID
if (!defined('PAYHERE_MERCHANT_ID')) {
    define('PAYHERE_MERCHANT_ID', getenv('PAYHERE_MERCHANT_ID') ?: '1238330');
}

// PayHere Sandbox Merchant Secret
// CRITICAL: MUST REMAIN SERVER-SIDE ONLY. NEVER EXPOSE IN FRONTEND CODE OR JSON.
if (!defined('PAYHERE_MERCHANT_SECRET')) {
    define('PAYHERE_MERCHANT_SECRET', getenv('PAYHERE_MERCHANT_SECRET') ?: 'MzA5MDEyNjg0NDEwOTkwMjU0MzQyMjY0MzEyODc0MTQ3NjIxOTkx');
}

// PayHere registered domain / host (Sandbox domain)
if (!defined('PAYHERE_DOMAIN')) {
    define('PAYHERE_DOMAIN', getenv('PAYHERE_DOMAIN') ?: 'localhost');
}

// Optional public HTTPS notification base URL for PayHere Sandbox IPN testing (e.g. temporary HTTPS tunnel)
// If set, this is used ONLY for notify_url. Storefront, return_url, and cancel_url remain on localhost.
if (!defined('PAYHERE_NOTIFY_BASE_URL')) {
    $envNotifyBase = getenv('PAYHERE_NOTIFY_BASE_URL') ?: getenv('PAYHERE_NOTIFY_URL');
    define('PAYHERE_NOTIFY_BASE_URL', $envNotifyBase ? rtrim($envNotifyBase, '/') : null);
}

// Integration mode: true for PayHere Sandbox testing, false for Live production
if (!defined('PAYHERE_SANDBOX_MODE')) {
    define('PAYHERE_SANDBOX_MODE', filter_var(getenv('PAYHERE_SANDBOX_MODE') ?: 'true', FILTER_VALIDATE_BOOLEAN));
}

// Store currency code
if (!defined('PAYHERE_CURRENCY')) {
    define('PAYHERE_CURRENCY', 'LKR');
}

/**
 * Generate PayHere checkout checksum hash.
 * Official Formula:
 *   hash = strtoupper(md5(merchant_id + order_id + amountFormatted + currency + strtoupper(md5(merchant_secret))))
 *
 * @param string $merchantId
 * @param string $orderId
 * @param float  $amount
 * @param string $currency
 * @param string $merchantSecret
 * @return string
 */
function payhere_generate_hash(string $merchantId, string $orderId, float $amount, string $currency, string $merchantSecret): string {
    $formattedAmount = number_format($amount, 2, '.', '');
    $hashedSecret = strtoupper(md5($merchantSecret));
    return strtoupper(md5($merchantId . $orderId . $formattedAmount . $currency . $hashedSecret));
}

/**
 * Verify PayHere server-to-server notification signature (md5sig).
 * Official Formula:
 *   local_md5sig = strtoupper(md5(merchant_id + order_id + payhere_amount + payhere_currency + status_code + strtoupper(md5(merchant_secret))))
 *
 * @param string $merchantId
 * @param string $orderId
 * @param string $payhereAmount
 * @param string $payhereCurrency
 * @param string $statusCode
 * @param string $receivedSignature
 * @param string $merchantSecret
 * @return bool
 */
function payhere_verify_signature(string $merchantId, string $orderId, string $payhereAmount, string $payhereCurrency, string $statusCode, string $receivedSignature, string $merchantSecret): bool {
    $hashedSecret = strtoupper(md5($merchantSecret));
    $expectedSignature = strtoupper(md5($merchantId . $orderId . $payhereAmount . $payhereCurrency . $statusCode . $hashedSecret));
    return hash_equals($expectedSignature, strtoupper(trim($receivedSignature)));
}

/**
 * Construct the secure PayHere notification (IPN) webhook URL.
 *
 * Rules:
 * 1. If PAYHERE_NOTIFY_BASE_URL is configured, use it to construct a secure HTTPS notification URL.
 * 2. Enforces HTTPS scheme.
 * 3. Guarantees the `/KukiCakes-Website/api/payhere-notify.php` path.
 * 4. Does not trust arbitrary client headers.
 * 5. Defaults to the local application URL when no override is configured.
 *
 * @param string $defaultBaseAppUrl Default local application base URL (e.g. http://localhost/KukiCakes-Website)
 * @return string Full, secure notification endpoint URL
 */
function payhere_get_notify_url(string $defaultBaseAppUrl): string {
    $configured = defined('PAYHERE_NOTIFY_BASE_URL') && PAYHERE_NOTIFY_BASE_URL ? trim((string) PAYHERE_NOTIFY_BASE_URL) : '';
    if ($configured === '') {
        return rtrim($defaultBaseAppUrl, '/') . '/api/payhere-notify.php';
    }

    // Enforce https:// scheme
    if (preg_match('#^https?://#i', $configured)) {
        $configured = preg_replace('#^http://#i', 'https://', $configured);
    } else {
        $configured = 'https://' . ltrim($configured, '/');
    }

    $parsed = parse_url($configured);
    $host = $parsed['host'] ?? '';
    $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
    $path = trim($parsed['path'] ?? '', '/');

    // Case 1: Configured path already ends with api/payhere-notify.php
    if (str_ends_with($path, 'api/payhere-notify.php')) {
        return 'https://' . $host . $port . '/' . $path;
    }

    // Case 2: Configured path starts with KukiCakes-Website
    if (str_starts_with($path, 'KukiCakes-Website')) {
        return 'https://' . $host . $port . '/' . rtrim($path, '/') . '/api/payhere-notify.php';
    }

    // Case 3: Bare tunnel host (e.g. https://xxx.ngrok-free.app)
    return 'https://' . $host . $port . '/KukiCakes-Website/api/payhere-notify.php';
}

