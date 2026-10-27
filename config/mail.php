<?php
/**
 * KúkiCakes — Email & SMTP Configuration (config/mail.php)
 *
 * Provides secure server-side SMTP mail transmission for transactional emails
 * (such as password resets and order notifications).
 *
 * IMPORTANT SECURITY RULES:
 * 1. This file resides server-side and must NEVER be exposed to clients.
 * 2. Real SMTP credentials must NEVER be committed to Git.
 * 3. Local overrides should be placed in `config/mail.local.php` (git-ignored)
 *    or provided via environment variables.
 */

// Prevent direct execution via web request
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'mail.php') {
    http_response_code(403);
    exit('Access forbidden.');
}

// Check for optional local environment override file (git-ignored, never committed)
$localMailConfig = __DIR__ . '/mail.local.php';
if (file_exists($localMailConfig)) {
    require_once $localMailConfig;
}

// ---------------------------------------------------------------
// SMTP Credentials & Settings
// ---------------------------------------------------------------

if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
}

if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: 587));
}

if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls')); // 'tls', 'ssl', or 'none'
}

if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
}

if (!defined('SMTP_PASSWORD')) {
    define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
}

if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'orders@kukicakes.lk');
}

if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'KúkiCakes Colombo');
}

if (!defined('SMTP_TIMEOUT')) {
    define('SMTP_TIMEOUT', (int) (getenv('SMTP_TIMEOUT') ?: 10));
}

/**
 * Lightweight, self-contained native SMTP mailer client.
 * Does not require external Composer packages or third-party extensions.
 */
class KukiMailer {
    /**
     * Check if outbound SMTP sending is configured.
     */
    public static function isConfigured(): bool {
        return !empty(SMTP_HOST);
    }

    /**
     * Send an email via configured SMTP.
     *
     * @param string $toEmail   Recipient email address
     * @param string $toName    Recipient display name
     * @param string $subject   Email subject line
     * @param string $htmlBody  Rich HTML email body
     * @param string $textBody  Plain text fallback body
     * @return array ['attempted' => bool, 'success' => bool, 'configured' => bool, 'message' => string]
     */
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): array {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return [
                'attempted'  => false,
                'success'    => false,
                'configured' => self::isConfigured(),
                'message'    => 'Invalid recipient email address.',
            ];
        }

        if (!self::isConfigured()) {
            // In local development when outbound SMTP credentials are not configured,
            // persist the transactional email to the secure local scratch directory for testing/review.
            $mailDir = dirname(__DIR__) . '/scratch/mail_log';
            if (!is_dir($mailDir)) {
                @mkdir($mailDir, 0755, true);
            }
            $timestamp = date('Y-m-d_H-i-s');
            $safeFile = $mailDir . "/email_{$timestamp}_" . bin2hex(random_bytes(4)) . ".html";
            $emailContent = "<!-- To: {$toName} <{$toEmail}>\n     Subject: {$subject}\n     Date: " . date('r') . " -->\n" . $htmlBody;
            @file_put_contents($safeFile, $emailContent);
            @file_put_contents($mailDir . "/latest_email.html", $emailContent);

            // Extract reset URL if present for quick developer access
            if (preg_match('#https?://[^\s<>"\']+/reset-password\.html\?token=[a-f0-9]+#i', $htmlBody, $m)) {
                @file_put_contents($mailDir . "/latest_reset_link.txt", $m[0]);
            }

            error_log("[KukiCakes Mailer] SMTP_HOST is not configured. Email saved locally to scratch/mail_log/latest_email.html");
            return [
                'attempted'  => true,
                'success'    => true,
                'configured' => false,
                'saved_file' => $safeFile,
                'message'    => 'SMTP service is not configured. Email saved locally to scratch/mail_log/latest_email.html for inspection.',
            ];
        }

        $host       = SMTP_HOST;
        $port       = SMTP_PORT;
        $encryption = SMTP_ENCRYPTION;
        $username   = SMTP_USERNAME;
        $password   = SMTP_PASSWORD;
        $fromEmail  = SMTP_FROM_EMAIL;
        $fromName   = SMTP_FROM_NAME;
        $timeout    = SMTP_TIMEOUT;

        $transport = ($encryption === 'ssl') ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ],
        ]);

        $socket = @stream_socket_client($transport, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            $err = "Could not connect to SMTP server {$host}:{$port} ($errno: $errstr)";
            error_log('[KukiCakes Mailer] ' . $err);
            return [
                'attempted'  => true,
                'success'    => false,
                'configured' => true,
                'message'    => $err,
            ];
        }

        stream_set_timeout($socket, $timeout);

        try {
            // 1. Initial banner (expect 220)
            self::readExpectedResponse($socket, [220], 'Connection banner');

            // 2. EHLO
            $clientHost = gethostname() ?: 'localhost';
            self::sendCommand($socket, "EHLO {$clientHost}", [250]);

            // 3. STARTTLS if configured
            if ($encryption === 'tls') {
                self::sendCommand($socket, "STARTTLS", [220]);
                $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$crypto) {
                    throw new RuntimeException("Failed to negotiate TLS encryption with SMTP server.");
                }
                // Resend EHLO after encryption upgrade
                self::sendCommand($socket, "EHLO {$clientHost}", [250]);
            }

            // 4. AUTH LOGIN if credentials provided
            if (!empty($username)) {
                self::sendCommand($socket, "AUTH LOGIN", [334]);
                self::sendCommand($socket, base64_encode($username), [334]);
                self::sendCommand($socket, base64_encode($password), [235]);
            }

            // 5. MAIL FROM
            self::sendCommand($socket, "MAIL FROM:<{$fromEmail}>", [250]);

            // 6. RCPT TO
            self::sendCommand($socket, "RCPT TO:<{$toEmail}>", [250, 251]);

            // 7. DATA
            self::sendCommand($socket, "DATA", [354]);

            // 8. Send MIME payload
            $mime = self::buildMimeMessage($fromEmail, $fromName, $toEmail, $toName, $subject, $htmlBody, $textBody);
            fwrite($socket, $mime . "\r\n.\r\n");
            self::readExpectedResponse($socket, [250], 'Message acceptance');

            // 9. QUIT
            self::sendCommand($socket, "QUIT", [221]);
            fclose($socket);

            return [
                'attempted'  => true,
                'success'    => true,
                'configured' => true,
                'message'    => 'Message accepted by SMTP server for delivery.',
            ];
        } catch (Exception $e) {
            @fclose($socket);
            $safeError = $e->getMessage();
            error_log('[KukiCakes Mailer] SMTP delivery failure: ' . $safeError);
            return [
                'attempted'  => true,
                'success'    => false,
                'configured' => true,
                'message'    => $safeError,
            ];
        }
    }

    private static function readExpectedResponse($socket, array $expectedCodes, string $stage): string {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $response .= $line;
            if (preg_match('/^[0-9]{3} /', $line)) {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException("SMTP stage '{$stage}' failed with code {$code}: " . trim($response));
        }
        return $response;
    }

    private static function sendCommand($socket, string $cmd, array $expectedCodes): string {
        fwrite($socket, $cmd . "\r\n");
        return self::readExpectedResponse($socket, $expectedCodes, $cmd);
    }

    public static function buildMimeMessage(string $fromEmail, string $fromName, string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): string {
        $boundary = '=_kuki_' . md5(uniqid(microtime(), true));
        $date     = date('r');
        $msgId    = '<' . md5(uniqid(microtime(), true)) . '@' . (gethostname() ?: 'kukicakes.lk') . '>';

        $headers = [
            "Date: {$date}",
            "Message-ID: {$msgId}",
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>",
            "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        ];

        $parts = [
            "--{$boundary}",
            "Content-Type: text/plain; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
            "",
            chunk_split(base64_encode($textBody)),
            "--{$boundary}",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
            "",
            chunk_split(base64_encode($htmlBody)),
            "--{$boundary}--",
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $parts);
    }
}
