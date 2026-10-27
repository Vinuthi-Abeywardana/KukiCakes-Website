<?php
/**
 * KúkiCakes — Customer Custom Cake Inquiry API (api/inquiries.php)
 *
 * Handles submission of custom cake inquiries from both guests and logged-in customers.
 *
 * HTTP Methods:
 * - POST: Submit a new custom cake inquiry (supports multipart/form-data with optional image).
 *
 * Security & Validation:
 * - Session customer_id derivation; submitted customer_id parameter is strictly ignored.
 * - Server-side validation of name, phone, cake type, event date (Asia/Colombo timezone).
 * - Optional field validation: email format, servings (min 4), supported cake types, flavors.
 * - Secure reference image handling: MIME check (JPEG/PNG/WebP), <= 5 MB, random filename.
 * - Rollback on failure with orphaned file cleanup.
 */

require_once __DIR__ . '/bootstrap.php';

// Only POST method is permitted
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    respond([
        'success' => false,
        'message' => 'Method Not Allowed. POST is required.'
    ], 405);
}

// ─────────────────────────────────────────────────────────────────
// Anti-Spam Protections: Honeypot & Rate Limiting
// ─────────────────────────────────────────────────────────────────

// 1. Honeypot check (transparent bot-trap field)
$honeypot = trim((string) ($_POST['cake_special_code'] ?? ''));
if ($honeypot !== '') {
    respond(['success' => false, 'message' => 'Spam submission detected.'], 422);
}

// 2. Session & IP rate limiting
$now = time();

// Session cooldown (minimum 2 seconds between successful submissions)
if (isset($_SESSION['last_inquiry_submit']) && ($now - (int) $_SESSION['last_inquiry_submit']) < 2) {
    respond(['success' => false, 'message' => 'Please wait a moment before submitting another inquiry.'], 429);
}

// Session burst limit (max 5 submissions per rolling 10-minute window)
$sessionHistory = $_SESSION['inquiry_history'] ?? [];
if (is_array($sessionHistory)) {
    $sessionHistory = array_filter($sessionHistory, function ($ts) use ($now) {
        return ($now - (int) $ts) < 600;
    });
    if (count($sessionHistory) >= 5) {
        respond(['success' => false, 'message' => 'Too many inquiries submitted. Please wait a few minutes before submitting another inquiry.'], 429);
    }
}

// IP burst limit (max 10 submissions per rolling 10-minute window)
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$ipHash = md5($clientIp);
$ipRateFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kuki_inq_rate_' . $ipHash . '.json';
$ipHistory = [];
if (file_exists($ipRateFile)) {
    $rawRate = @file_get_contents($ipRateFile);
    $decodedRate = $rawRate ? json_decode($rawRate, true) : null;
    if (is_array($decodedRate)) {
        $ipHistory = array_filter($decodedRate, function ($ts) use ($now) {
            return ($now - (int) $ts) < 600;
        });
        if (count($ipHistory) >= 10) {
            respond(['success' => false, 'message' => 'Rate limit exceeded. Please wait a few minutes before submitting another inquiry.'], 429);
        }
    }
}

// ─────────────────────────────────────────────────────────────────
// 1. Authenticated Customer Linking (Session only)
// ─────────────────────────────────────────────────────────────────
$customerId = null;
if (isAuthenticated()) {
    $customerId = (int) $_SESSION['customer_id'];
}

// ─────────────────────────────────────────────────────────────────
// 2. Extract and Validate Form Fields
// ─────────────────────────────────────────────────────────────────
$name       = trim((string) ($_POST['name'] ?? ''));
$phone      = trim((string) ($_POST['phone'] ?? ''));
$email      = trim((string) ($_POST['email'] ?? ''));
$cakeType   = trim((string) ($_POST['cakeType'] ?? $_POST['cake_type'] ?? ''));
$eventDate  = trim((string) ($_POST['eventDate'] ?? $_POST['event_date'] ?? ''));
$servingsRaw= trim((string) ($_POST['servings'] ?? $_POST['no_of_servings'] ?? ''));
$flavorRaw  = trim((string) ($_POST['flavor'] ?? ''));
$budgetRaw  = trim((string) ($_POST['budget'] ?? $_POST['budget_range'] ?? ''));
$design     = trim((string) ($_POST['design'] ?? $_POST['design_description'] ?? ''));

// ── Malformed Input & Null Byte Check ──
$inputStrings = [$name, $phone, $email, $cakeType, $eventDate, $servingsRaw, $flavorRaw, $budgetRaw, $design];
foreach ($inputStrings as $inputStr) {
    if (str_contains($inputStr, "\0")) {
        respond(['success' => false, 'message' => 'Malformed input detected.'], 400);
    }
}

// ── Required Fields Validation ──
if ($name === '') {
    respond(['success' => false, 'message' => 'Your name is required.'], 422);
}
if (mb_strlen($name) < 2) {
    respond(['success' => false, 'message' => 'Your name must be at least 2 characters.'], 422);
}
if (mb_strlen($name) > 100) {
    respond(['success' => false, 'message' => 'Name cannot exceed 100 characters.'], 422);
}

if ($phone === '') {
    respond(['success' => false, 'message' => 'Phone / WhatsApp number is required.'], 422);
}
if (mb_strlen($phone) > 20) {
    respond(['success' => false, 'message' => 'Phone number cannot exceed 20 characters.'], 422);
}
if (!preg_match('/^[0-9+\s\-().]{7,20}$/', $phone)) {
    respond(['success' => false, 'message' => 'Please enter a valid phone number (at least 7 digits).'], 422);
}

// ── Optional Email Validation ──
$contactEmail = null;
if ($email !== '') {
    if (mb_strlen($email) > 150) {
        respond(['success' => false, 'message' => 'Email address cannot exceed 150 characters.'], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
    }
    $contactEmail = $email;
}

// ── Cake Type Validation ──
if ($cakeType === '') {
    respond(['success' => false, 'message' => 'Please select a cake type.'], 422);
}

$allowedCakeTypes = [
    'Anniversary Cake',
    'Birthday Cake',
    'Christmas Cake',
    'Cupcake',
    'Engagement Cake',
    'Graduation Cake',
    'Number Cake',
    'Party Cake',
    'Wedding Cake',
];

$matchedCakeType = null;
foreach ($allowedCakeTypes as $allowed) {
    if (strcasecmp($cakeType, $allowed) === 0) {
        $matchedCakeType = $allowed;
        break;
    }
}
if ($matchedCakeType === null) {
    respond([
        'success' => false,
        'message' => 'Invalid cake type selected.',
        'allowed_cake_types' => $allowedCakeTypes
    ], 422);
}

// ── Event Date Validation (48-Hour Advance Notice in Asia/Colombo) ──
if ($eventDate === '') {
    respond(['success' => false, 'message' => 'Event date is required.'], 422);
}

$tzColombo = new DateTimeZone('Asia/Colombo');
$dateObj = DateTime::createFromFormat('Y-m-d', $eventDate, $tzColombo);
if (!$dateObj || $dateObj->format('Y-m-d') !== $eventDate) {
    respond(['success' => false, 'message' => 'Invalid event date format (YYYY-MM-DD expected).'], 422);
}

$todayColombo = new DateTime('today', $tzColombo);
$todayStr = $todayColombo->format('Y-m-d');
if ($eventDate < $todayStr) {
    respond(['success' => false, 'message' => 'Event date cannot be in the past.'], 422);
}

$minDate = (clone $todayColombo)->modify('+2 days');
$minDateStr = $minDate->format('Y-m-d');
if ($eventDate < $minDateStr) {
    $minFormatted = $minDate->format('d/m/Y');
    respond(['success' => false, 'message' => "Inquiries require at least 48 hours advance notice (earliest available: {$minFormatted})."], 422);
}

$maxFutureColombo = (new DateTime('+2 years', $tzColombo))->format('Y-m-d');
if ($eventDate > $maxFutureColombo) {
    respond(['success' => false, 'message' => 'Event date cannot be more than 2 years in advance.'], 422);
}

// ── Servings Validation ──
$noOfServings = null;
if ($servingsRaw !== '') {
    if (!filter_var($servingsRaw, FILTER_VALIDATE_INT)) {
        respond(['success' => false, 'message' => 'Number of servings must be a valid whole number.'], 422);
    }
    $servingsInt = (int) $servingsRaw;
    if ($servingsInt < 4) {
        respond(['success' => false, 'message' => 'Number of servings must be at least 4.'], 422);
    }
    if ($servingsInt > 10000) {
        respond(['success' => false, 'message' => 'Number of servings is too large.'], 422);
    }
    $noOfServings = $servingsInt;
}

// ── Flavor Validation & Mapping ──
// "Not sure yet" maps to NULL as per database convention and requirement.
$flavorId = null;
if ($flavorRaw !== '' && strcasecmp($flavorRaw, 'Not sure yet') !== 0) {
    try {
        $stmtFlavor = $pdo->prepare('SELECT flavor_id, flavor_name FROM `flavor` WHERE LOWER(flavor_name) = LOWER(?) LIMIT 1');
        $stmtFlavor->execute([$flavorRaw]);
        $foundFlavor = $stmtFlavor->fetch(PDO::FETCH_ASSOC);

        if ($foundFlavor) {
            // If flavor matches 'Not Sure Yet' record (flavor_id 1), map to NULL
            if (strcasecmp($foundFlavor['flavor_name'], 'Not Sure Yet') === 0) {
                $flavorId = null;
            } else {
                $flavorId = (int) $foundFlavor['flavor_id'];
            }
        } else {
            respond(['success' => false, 'message' => "Invalid flavor '{$flavorRaw}' selected."], 422);
        }
    } catch (PDOException $e) {
        respond(['success' => false, 'message' => 'Database error verifying flavor selection.'], 500);
    }
}

// ── Budget Range Normalization ──
$allowedBudgets = [
    'Prefer not to say',
    'Below Rs. 10,000',
    'Rs. 10,000–25,000',
    'Rs. 10,000-25,000',
    'Rs. 25,000–50,000',
    'Rs. 25,000-50,000',
    'Above Rs. 50,000',
];
$budgetRange = null;
if ($budgetRaw !== '') {
    $normalizedBudget = str_replace('-', '–', $budgetRaw);
    $foundBudget = false;
    foreach ($allowedBudgets as $b) {
        if (strcasecmp(str_replace('-', '–', $b), $normalizedBudget) === 0) {
            $budgetRange = str_replace('-', '–', $b);
            $foundBudget = true;
            break;
        }
    }
    if (!$foundBudget) {
        // Fall back to safely trimmed budget if within 50 chars
        if (mb_strlen($budgetRaw) <= 50) {
            $budgetRange = $budgetRaw;
        } else {
            respond(['success' => false, 'message' => 'Budget range value is too long.'], 422);
        }
    }
}

// ── Design Description ──
$designDescription = null;
if ($design !== '') {
    if (mb_strlen($design) > 5000) {
        respond(['success' => false, 'message' => 'Design description cannot exceed 5,000 characters.'], 422);
    }
    $designDescription = $design;
}

// ─────────────────────────────────────────────────────────────────
// 3. Status Lookup (Pending)
// ─────────────────────────────────────────────────────────────────
$pendingStatusId = 1;
try {
    $stmtStatus = $pdo->prepare("SELECT status_id FROM `inquiry_status` WHERE status_name = 'Pending' LIMIT 1");
    $stmtStatus->execute();
    $foundStatus = $stmtStatus->fetchColumn();
    if ($foundStatus !== false) {
        $pendingStatusId = (int) $foundStatus;
    }
} catch (PDOException $e) {
    // Default to 1 if lookup fails
    $pendingStatusId = 1;
}

// ─────────────────────────────────────────────────────────────────
// 4. Reference Image Upload Handling
// ─────────────────────────────────────────────────────────────────
$relativeImagePath = null;
$savedAbsoluteFilePath = null;

if (isset($_FILES['inspiration']) && $_FILES['inspiration']['error'] !== UPLOAD_ERR_NO_FILE) {
    $fileError = $_FILES['inspiration']['error'];

    if ($fileError === UPLOAD_ERR_INI_SIZE || $fileError === UPLOAD_ERR_FORM_SIZE) {
        respond(['success' => false, 'message' => 'Image file size exceeds limit (maximum 5 MB).'], 422);
    }
    if ($fileError !== UPLOAD_ERR_OK) {
        respond(['success' => false, 'message' => 'An error occurred during image upload (code: ' . (int) $fileError . ').'], 400);
    }

    $tmpPath = $_FILES['inspiration']['tmp_name'];
    $fileSize = $_FILES['inspiration']['size'];

    // Enforce 5 MB limit
    if ($fileSize > 5 * 1024 * 1024) {
        respond(['success' => false, 'message' => 'Image file size exceeds limit (maximum 5 MB).'], 422);
    }

    // Validate MIME type with finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($tmpPath);

    $allowedMimes = [
        'image/jpeg' => '.jpg',
        'image/png'  => '.png',
        'image/webp' => '.webp',
    ];

    if (!isset($allowedMimes[$detectedMime])) {
        respond([
            'success' => false,
            'message' => 'Invalid image format. Allowed formats: JPEG, PNG, and WebP.'
        ], 422);
    }

    // Verify it is a valid image using getimagesize
    $imgDetails = @getimagesize($tmpPath);
    if ($imgDetails === false) {
        respond(['success' => false, 'message' => 'Uploaded file is not a valid image.'], 422);
    }

    // Prepare destination directory
    $uploadDir = __DIR__ . '/../uploads/inquiries';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            respond(['success' => false, 'message' => 'Failed to initialize upload directory.'], 500);
        }
    }

    // Ensure .htaccess prevents script execution and directory browsing
    $htaccessFile = $uploadDir . '/.htaccess';
    if (!file_exists($htaccessFile)) {
        $htaccessContent = "# Disable script execution and directory browsing in inquiries uploads directory\n"
            . "Options -ExecCGI -Indexes\n\n"
            . "<FilesMatch \"(?i)\\.(php|phtml|php3|php4|php5|php7|php8|phps|phar|inc|cgi|pl|exe|sh|bat|cmd|py|rb|shtml|asp|aspx|jsp|env|config|htaccess|htpasswd)$\">\n"
            . "    <IfModule !mod_authz_core.c>\n"
            . "        Order Allow,Deny\n"
            . "        Deny from all\n"
            . "    </IfModule>\n"
            . "    <IfModule mod_authz_core.c>\n"
            . "        Require all denied\n"
            . "    </IfModule>\n"
            . "</FilesMatch>\n\n"
            . "<IfModule mod_php.c>\n"
            . "    php_flag engine off\n"
            . "</IfModule>\n"
            . "<IfModule mod_php7.c>\n"
            . "    php_flag engine off\n"
            . "</IfModule>\n"
            . "<IfModule mod_php8.c>\n"
            . "    php_flag engine off\n"
            . "</IfModule>\n\n"
            . "RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .phar .cgi .pl .asp .aspx .shtml\n"
            . "SetHandler default-handler\n\n"
            . "<IfModule mod_headers.c>\n"
            . "    Header set X-Content-Type-Options \"nosniff\"\n"
            . "</IfModule>\n";
        @file_put_contents($htaccessFile, $htaccessContent);
    }

    $indexHtmlFile = $uploadDir . '/index.html';
    if (!file_exists($indexHtmlFile)) {
        @file_put_contents($indexHtmlFile, "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Directory access is forbidden.</h1></body></html>\n");
    }

    // Unpredictable random filename
    $ext = $allowedMimes[$detectedMime];
    $randomName = 'inq_' . bin2hex(random_bytes(16)) . $ext;
    $savedAbsoluteFilePath = $uploadDir . '/' . $randomName;

    if (!move_uploaded_file($tmpPath, $savedAbsoluteFilePath)) {
        respond(['success' => false, 'message' => 'Failed to store uploaded image on server.'], 500);
    }

    $relativeImagePath = 'uploads/inquiries/' . $randomName;
}

// ─────────────────────────────────────────────────────────────────
// 5. Database Insertion under Transaction
// ─────────────────────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    $stmtInsert = $pdo->prepare('
        INSERT INTO `inquiry` (
            customer_id,
            contact_name,
            contact_phone,
            contact_email,
            status_id,
            flavor_id,
            cake_type,
            event_date,
            no_of_servings,
            budget_range,
            design_description,
            inspiration_image,
            inquiry_date
        ) VALUES (
            :customer_id,
            :contact_name,
            :contact_phone,
            :contact_email,
            :status_id,
            :flavor_id,
            :cake_type,
            :event_date,
            :no_of_servings,
            :budget_range,
            :design_description,
            :inspiration_image,
            NOW()
        )
    ');

    $stmtInsert->execute([
        ':customer_id'        => $customerId,
        ':contact_name'       => $name,
        ':contact_phone'      => $phone,
        ':contact_email'      => $contactEmail,
        ':status_id'          => $pendingStatusId,
        ':flavor_id'          => $flavorId,
        ':cake_type'          => $matchedCakeType,
        ':event_date'         => $eventDate,
        ':no_of_servings'     => $noOfServings,
        ':budget_range'       => $budgetRange,
        ':design_description'=> $designDescription,
        ':inspiration_image'  => $relativeImagePath,
    ]);

    $inquiryId = (int) $pdo->lastInsertId();

    $pdo->commit();

    // ── Update Rate Limiting Counters Upon Successful Submission ──
    $_SESSION['last_inquiry_submit'] = $now;
    $sessionHistory[] = $now;
    $_SESSION['inquiry_history'] = $sessionHistory;

    $ipHistory[] = $now;
    @file_put_contents($ipRateFile, json_encode($ipHistory), LOCK_EX);

    respond([
        'success'    => true,
        'message'    => 'Thank you! Your custom cake inquiry has been received. KúkiCakes will contact you shortly.',
        'inquiry_id' => $inquiryId,
    ], 201);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Clean up uploaded image if DB insertion fails (prevent orphaned files)
    if ($savedAbsoluteFilePath && file_exists($savedAbsoluteFilePath)) {
        @unlink($savedAbsoluteFilePath);
    }

    // Safe error message to client without exposing SQL or file paths
    respond([
        'success' => false,
        'message' => 'Unable to submit your inquiry at this moment. Please try again or contact us directly.'
    ], 500);
}
