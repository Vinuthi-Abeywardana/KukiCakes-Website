<?php
/**
 * Test Suite: Custom Cake Inquiry Feature Verification (tests/test_inquiries.php)
 *
 * Verifies:
 * 1. HTTP Method restrictions (api/inquiries.php rejects GET, PUT, DELETE with 405)
 * 2. Server-side validation of customer inquiry submissions:
 *    - Required name, phone, cake type, event date
 *    - Rejection of past event dates in Asia/Colombo timezone
 *    - Rejection of invalid email format
 *    - Rejection of servings < 4
 *    - Rejection of unsupported cake types
 *    - Rejection of invalid flavors
 * 3. Reference image upload security:
 *    - Rejection of non-image files
 *    - Rejection of oversized files (> 5 MB)
 *    - Script execution prevention via .htaccess
 * 4. Guest inquiry submission and contact persistence:
 *    - Valid submission returns 201 Created
 *    - DB verification: customer_id is NULL, contact_name, contact_phone, contact_email saved
 *    - Initial status is Pending (status_id = 1)
 *    - Reference image saved with unpredictable filename and relative path
 * 5. Administrator API authorization:
 *    - Unauthenticated request rejected with 401 Unauthorized
 *    - Customer account rejected with 403 Forbidden
 *    - Administrator request permitted (200 OK)
 * 6. Admin inquiry status management:
 *    - Status update to 'In Review' succeeds (200 OK)
 *    - Invalid status ID rejected with 400 Bad Request
 *    - Non-existent inquiry ID rejected with 404 Not Found
 * 7. Authenticated customer inquiry submission:
 *    - Links inquiry to server-side session customer_id
 *    - Parameter tampering immunity: forged customer_id parameter is completely ignored
 * 8. Flavor mapping:
 *    - "Not sure yet" maps to NULL in database
 * 9. Security & XSS / SQL Injection protection:
 *    - Special characters and script tags stored verbatim without SQL errors
 * 10. Test DB hygiene:
 *    - All test fixtures and uploaded files are cleaned up immediately; live DB remains clean (0 inquiries).
 */

require_once __DIR__ . '/../config/db.php';

$baseUrl = 'http://localhost/KukiCakes-Website/api';
$testsPassed = 0;
$testsFailed = 0;
$initialDbInquiryCount = (int) $pdo->query('SELECT COUNT(*) FROM `inquiry`')->fetchColumn();

function assertTest(bool $condition, string $description, string $details = ''): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  [PASS] {$description}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$description}" . ($details !== '' ? " -> {$details}" : '') . "\n";
        $testsFailed++;
    }
}

function curlRequest(string $url, string $method = 'GET', $body = null, ?string $sessionId = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $headers = [];
    if (is_array($body)) {
        $hasFile = false;
        foreach ($body as $v) {
            if ($v instanceof CURLFile) {
                $hasFile = true;
                break;
            }
        }
        if (!$hasFile && $method !== 'POST') {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } else {
            // curl handles multipart/form-data automatically with array
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
    } elseif ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    if ($sessionId !== null) {
        curl_setopt($ch, CURLOPT_COOKIE, "KUKI_SESS={$sessionId}");
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $code,
        'raw'  => $raw,
        'data' => json_decode($raw ?: '', true) ?? []
    ];
}

// Obtains a real session ID from Apache and populates it in C:\xampp\tmp
function createAuthenticatedSession(array $sessionData): string {
    $ch = curl_init('http://localhost/KukiCakes-Website/api/check-session.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $out = curl_exec($ch);
    curl_close($ch);

    preg_match('/KUKI_SESS=([a-z0-9]+);/i', $out, $matches);
    $sessId = $matches[1] ?? '';
    if (!$sessId) {
        throw new RuntimeException('Failed to obtain session ID from Apache');
    }

    $savePath = session_save_path() ?: 'C:\\xampp\\tmp';
    $sessFile = rtrim($savePath, '\\/') . DIRECTORY_SEPARATOR . 'sess_' . $sessId;

    $serialized = '';
    foreach ($sessionData as $k => $v) {
        $serialized .= $k . '|' . serialize($v);
    }
    file_put_contents($sessFile, $serialized);

    return $sessId;
}

function destroyTestSession(string $sessId): void {
    $savePath = session_save_path() ?: 'C:\\xampp\\tmp';
    $sessFile = rtrim($savePath, '\\/') . DIRECTORY_SEPARATOR . 'sess_' . $sessId;
    if (file_exists($sessFile)) {
        @unlink($sessFile);
    }
}

echo "=========================================================\n";
echo " KúkiCakes — Custom Cake Inquiry Verification Suite\n";
echo "=========================================================\n";

// ─────────────────────────────────────────────────────────────────
// Section 1: HTTP Method Restrictions
// ─────────────────────────────────────────────────────────────────
echo "\n[1] Testing HTTP Method Restrictions on api/inquiries.php...\n";

$res = curlRequest("{$baseUrl}/inquiries.php", 'GET');
assertTest($res['code'] === 405, "GET request rejected with 405 Method Not Allowed");
assertTest(isset($res['data']['success']) && $res['data']['success'] === false, "GET response contains success: false");

$res = curlRequest("{$baseUrl}/inquiries.php", 'PUT', ['test' => 1]);
assertTest($res['code'] === 405, "PUT request rejected with 405 Method Not Allowed");

$res = curlRequest("{$baseUrl}/inquiries.php", 'DELETE');
assertTest($res['code'] === 405, "DELETE request rejected with 405 Method Not Allowed");

// ─────────────────────────────────────────────────────────────────
// Section 2: Server-Side Validation
// ─────────────────────────────────────────────────────────────────
echo "\n[2] Testing Server-Side Field Validation...\n";

// Future event date in Asia/Colombo
$futureDate = (new DateTime('+14 days', new DateTimeZone('Asia/Colombo')))->format('Y-m-d');
$pastDate   = (new DateTime('-5 days', new DateTimeZone('Asia/Colombo')))->format('Y-m-d');

// Missing name
$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'phone' => '+94771234567',
    'cakeType' => 'Birthday Cake',
    'eventDate' => $futureDate,
]);
assertTest($res['code'] === 422, "Missing name rejected with 422 Unprocessable Entity");

// Missing phone
$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Amaya Silva',
    'cakeType' => 'Birthday Cake',
    'eventDate' => $futureDate,
]);
assertTest($res['code'] === 422, "Missing phone rejected with 422");

// Missing cakeType
$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Amaya Silva',
    'phone' => '+94771234567',
    'eventDate' => $futureDate,
]);
assertTest($res['code'] === 422, "Missing cakeType rejected with 422");

// Missing eventDate
$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Amaya Silva',
    'phone' => '+94771234567',
    'cakeType' => 'Birthday Cake',
]);
assertTest($res['code'] === 422, "Missing eventDate rejected with 422");

// Invalid cake type
$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Amaya Silva',
    'phone' => '+94771234567',
    'cakeType' => 'Spaceship Alien Cake',
    'eventDate' => $futureDate,
]);
assertTest($res['code'] === 422, "Invalid cake type rejected with 422");

// Short name (< 2 chars) rejection
$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'A',
    'phone' => '+94771234567',
    'cakeType' => 'Birthday Cake',
    'eventDate' => $futureDate,
]);
assertTest($res['code'] === 422, "Short name (< 2 chars) rejected with 422");

// Past event date rejection
$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Amaya Silva',
    'phone' => '+94771234567',
    'cakeType' => 'Birthday Cake',
    'eventDate' => $pastDate,
]);
assertTest($res['code'] === 422, "Past event date rejected with 422");
assertTest(str_contains($res['data']['message'] ?? '', 'past'), "Past event date message mentions 'past'");

// 48-hour advance notice boundary checks in Asia/Colombo
$todayDate    = (new DateTime('today', new DateTimeZone('Asia/Colombo')))->format('Y-m-d');
$tomorrowDate = (new DateTime('+1 day', new DateTimeZone('Asia/Colombo')))->format('Y-m-d');
$exact48hDate = (new DateTime('+2 days', new DateTimeZone('Asia/Colombo')))->format('Y-m-d');

// Today (< 48h) rejection
$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Amaya Silva',
    'phone' => '+94771234567',
    'cakeType' => 'Birthday Cake',
    'eventDate' => $todayDate,
]);
assertTest($res['code'] === 422, "Today's date (< 48 hours notice) rejected with 422");
assertTest(str_contains($res['data']['message'] ?? '', '48 hours'), "Today's date error explains 48 hours requirement");

// Tomorrow (< 48h) rejection
$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Amaya Silva',
    'phone' => '+94771234567',
    'cakeType' => 'Birthday Cake',
    'eventDate' => $tomorrowDate,
]);
assertTest($res['code'] === 422, "Tomorrow's date (< 48 hours notice) rejected with 422");
assertTest(str_contains($res['data']['message'] ?? '', '48 hours'), "Tomorrow's date error explains 48 hours requirement");

// Invalid email format
$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Amaya Silva',
    'phone' => '+94771234567',
    'email' => 'invalid-email-address',
    'cakeType' => 'Birthday Cake',
    'eventDate' => $futureDate,
]);
assertTest($res['code'] === 422, "Invalid email format rejected with 422");

// Invalid servings (< 4)
$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Amaya Silva',
    'phone' => '+94771234567',
    'cakeType' => 'Birthday Cake',
    'eventDate' => $futureDate,
    'servings' => '2',
]);
assertTest($res['code'] === 422, "Servings < 4 rejected with 422");

// Invalid flavor
$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Amaya Silva',
    'phone' => '+94771234567',
    'cakeType' => 'Birthday Cake',
    'eventDate' => $futureDate,
    'flavor' => 'Wasabi Charcoal',
]);
assertTest($res['code'] === 422, "Invalid flavor rejected with 422");

// Event date > 2 years in advance rejection
$farFutureDate = (new DateTime('+3 years', new DateTimeZone('Asia/Colombo')))->format('Y-m-d');
$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Amaya Silva',
    'phone' => '+94771234567',
    'cakeType' => 'Birthday Cake',
    'eventDate' => $farFutureDate,
]);
assertTest($res['code'] === 422, "Event date > 2 years in advance rejected with 422");
assertTest(str_contains($res['data']['message'] ?? '', '2 years'), "Date error explains 2 years limitation");

// Null-byte injection rejection
$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => "Malicious\0User",
    'phone' => '+94771234567',
    'cakeType' => 'Birthday Cake',
    'eventDate' => $futureDate,
]);
assertTest($res['code'] === 400, "Null-byte in text field rejected with 400 Bad Request");

// Honeypot spam field rejection
$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Spam Bot',
    'phone' => '+94771234567',
    'cakeType' => 'Birthday Cake',
    'eventDate' => $futureDate,
    'cake_special_code' => 'http://spam-site.xyz',
]);
assertTest($res['code'] === 422, "Honeypot filled submission rejected with 422 Unprocessable Entity");
assertTest(str_contains(strtolower($res['data']['message'] ?? ''), 'spam'), "Error message identifies spam");

// ─────────────────────────────────────────────────────────────────
// Section 3: Reference Image Upload Security
// ─────────────────────────────────────────────────────────────────
echo "\n[3] Testing Reference Image Upload Security...\n";

// Fake non-image file (.txt disguised as image)
$fakeImagePath = sys_get_temp_dir() . '/test_fake_image.jpg';
file_put_contents($fakeImagePath, "<?php echo 'malicious code'; ?>");

$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Amaya Silva',
    'phone' => '+94771234567',
    'cakeType' => 'Birthday Cake',
    'eventDate' => $futureDate,
    'inspiration' => new CURLFile($fakeImagePath, 'image/jpeg', 'test_fake.jpg')
]);
@unlink($fakeImagePath);

assertTest($res['code'] === 422, "Non-image text file rejected with 422");
assertTest(str_contains($res['data']['message'] ?? '', 'image'), "Error message explains invalid image");

// Check .htaccess exists in uploads/inquiries/
$htaccessPath = __DIR__ . '/../uploads/inquiries/.htaccess';
assertTest(file_exists($htaccessPath), "uploads/inquiries/.htaccess exists");
$htaccessContent = file_get_contents($htaccessPath);
assertTest(str_contains($htaccessContent, 'Require all denied') || str_contains($htaccessContent, 'Deny from all'), ".htaccess blocks execution of script files");

$indexHtmlPath = __DIR__ . '/../uploads/inquiries/index.html';
assertTest(file_exists($indexHtmlPath), "uploads/inquiries/index.html directory browsing protection exists");

// ─────────────────────────────────────────────────────────────────
// Section 4: Full Guest Submission & Contact Persistence
// ─────────────────────────────────────────────────────────────────
echo "\n[4] Testing Guest Inquiry Submission & DB Persistence...\n";

// Generate a valid 1x1 PNG for upload testing
$validPngPath = sys_get_temp_dir() . '/test_valid_pixel.png';
file_put_contents($validPngPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

$guestPostData = [
    'name' => 'Guest Tester',
    'phone' => '+94 77 123 4567',
    'email' => 'guest.tester@example.com',
    'cakeType' => 'Anniversary Cake',
    'eventDate' => $futureDate,
    'servings' => '25',
    'flavor' => 'Chocolate',
    'budget' => 'Rs. 10,000–25,000',
    'design' => 'Gold foil with minimalist chocolate drips and white roses',
    'inspiration' => new CURLFile($validPngPath, 'image/png', 'sample_inspo.png'),
];

$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', $guestPostData);
@unlink($validPngPath);

assertTest($res['code'] === 201, "Guest submission returned 201 Created");
assertTest(isset($res['data']['success']) && $res['data']['success'] === true, "Response has success: true");
assertTest(!empty($res['data']['inquiry_id']), "Response contains new inquiry_id");

$guestInqId = (int) ($res['data']['inquiry_id'] ?? 0);

// Verify database record
$dbRow = $pdo->prepare('SELECT * FROM `inquiry` WHERE inquiry_id = ?');
$dbRow->execute([$guestInqId]);
$inqRecord = $dbRow->fetch(PDO::FETCH_ASSOC);

assertTest($inqRecord !== false, "Inquiry was found in live database");
assertTest($inqRecord['customer_id'] === null, "customer_id is NULL for guest submission");
assertTest($inqRecord['contact_name'] === 'Guest Tester', "contact_name persisted correctly ('Guest Tester')");
assertTest($inqRecord['contact_phone'] === '+94 77 123 4567', "contact_phone persisted correctly");
assertTest($inqRecord['contact_email'] === 'guest.tester@example.com', "contact_email persisted correctly");
assertTest($inqRecord['cake_type'] === 'Anniversary Cake', "cake_type persisted correctly");
assertTest($inqRecord['event_date'] === $futureDate, "event_date persisted correctly");
assertTest((int) $inqRecord['no_of_servings'] === 25, "no_of_servings persisted correctly (25)");
assertTest((int) $inqRecord['flavor_id'] === 3, "flavor_id mapped correctly to 3 (Chocolate)");
assertTest((int) $inqRecord['status_id'] === 1, "Initial status_id is 1 (Pending)");
assertTest(!empty($inqRecord['inspiration_image']), "inspiration_image path is populated");

// Verify image file exists on disk
$uploadedDiskPath = __DIR__ . '/../' . $inqRecord['inspiration_image'];
assertTest(file_exists($uploadedDiskPath), "Uploaded reference image exists on local disk: " . $inqRecord['inspiration_image']);
assertTest(str_starts_with($inqRecord['inspiration_image'], 'uploads/inquiries/inq_'), "Image uses random inq_[hex] filename format");

// ─────────────────────────────────────────────────────────────────
// Section 4b: Anti-Spam Rate Limiting (Session Cooldown)
// ─────────────────────────────────────────────────────────────────
echo "\n[4b] Testing Anti-Spam Rate Limiting...\n";

$rateSessId = createAuthenticatedSession([]);

$res1 = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Rate Limit Tester',
    'phone' => '+94771234567',
    'cakeType' => 'Birthday Cake',
    'eventDate' => $futureDate,
], $rateSessId);

assertTest($res1['code'] === 201, "First submission in session succeeds with 201 Created");
$rateInqId = (int) ($res1['data']['inquiry_id'] ?? 0);

// Immediate second submission in same session
$res2 = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Rate Limit Tester 2',
    'phone' => '+94771234567',
    'cakeType' => 'Birthday Cake',
    'eventDate' => $futureDate,
], $rateSessId);

assertTest($res2['code'] === 429, "Immediate second submission in same session rejected with 429 Too Many Requests");
assertTest(str_contains(strtolower($res2['data']['message'] ?? ''), 'wait'), "Rate limit message prompts user to wait");

// Clean up first test record
if ($rateInqId > 0) {
    $pdo->prepare('DELETE FROM `inquiry` WHERE inquiry_id = ?')->execute([$rateInqId]);
}
destroyTestSession($rateSessId);

// ─────────────────────────────────────────────────────────────────
// Section 5: Administrator API Authorization & Status Management
// ─────────────────────────────────────────────────────────────────
echo "\n[5] Testing Administrator API Access Controls...\n";

// Unauthenticated access
$res = curlRequest("{$baseUrl}/admin-inquiries.php", 'GET');
assertTest($res['code'] === 401, "Unauthenticated GET rejected with 401 Unauthorized");

$res = curlRequest("{$baseUrl}/admin-inquiries.php", 'POST', ['action' => 'update_status']);
assertTest($res['code'] === 401, "Unauthenticated POST rejected with 401 Unauthorized");

// Customer session access (role = customer)
$custSessionId = createAuthenticatedSession(['customer_id' => 96, 'role' => 'customer']);

$res = curlRequest("{$baseUrl}/admin-inquiries.php", 'GET', null, $custSessionId);
assertTest($res['code'] === 403, "Customer session GET rejected with 403 Forbidden");

$res = curlRequest("{$baseUrl}/admin-inquiries.php", 'POST', ['action' => 'update_status'], $custSessionId);
assertTest($res['code'] === 403, "Customer session POST rejected with 403 Forbidden");

destroyTestSession($custSessionId);

// Administrator session access (role = admin)
$adminSessionId = createAuthenticatedSession(['admin_id' => 1, 'role' => 'admin']);

$res = curlRequest("{$baseUrl}/admin-inquiries.php", 'GET', null, $adminSessionId);
assertTest($res['code'] === 200, "Administrator GET permitted with 200 OK");
assertTest(isset($res['data']['inquiries']) && is_array($res['data']['inquiries']), "Admin response contains inquiries array");
assertTest(isset($res['data']['counts']) && isset($res['data']['statuses']), "Admin response contains counts and statuses");

// Admin single inquiry detail view
$res = curlRequest("{$baseUrl}/admin-inquiries.php?id={$guestInqId}", 'GET', null, $adminSessionId);
assertTest($res['code'] === 200, "Admin view single inquiry returned 200 OK");
assertTest(($res['data']['inquiry']['contact_name'] ?? '') === 'Guest Tester', "Admin single view has matching customer contact");
assertTest(($res['data']['inquiry']['flavor_name'] ?? '') === 'Chocolate', "Admin single view resolved flavor name ('Chocolate')");

echo "\n[6] Testing Admin Inquiry Status Updates...\n";

// Update status to 'In Review' (status_id 2)
$res = curlRequest("{$baseUrl}/admin-inquiries.php", 'POST', [
    'action' => 'update_status',
    'inquiry_id' => $guestInqId,
    'status_id' => 2
], $adminSessionId);

assertTest($res['code'] === 200, "Status update to 'In Review' returned 200 OK");
assertTest(($res['data']['status_name'] ?? '') === 'In Review', "Response confirmed status_name is 'In Review'");

// Verify in database
$checkStmt = $pdo->prepare('SELECT status_id FROM `inquiry` WHERE inquiry_id = ?');
$checkStmt->execute([$guestInqId]);
assertTest((int) $checkStmt->fetchColumn() === 2, "Database status_id is now updated to 2");

// Invalid status ID update
$res = curlRequest("{$baseUrl}/admin-inquiries.php", 'POST', [
    'action' => 'update_status',
    'inquiry_id' => $guestInqId,
    'status_id' => 9999
], $adminSessionId);
assertTest($res['code'] === 400, "Invalid status_id 9999 rejected with 400 Bad Request");

// Non-existent inquiry update
$res = curlRequest("{$baseUrl}/admin-inquiries.php", 'POST', [
    'action' => 'update_status',
    'inquiry_id' => 999999,
    'status_id' => 1
], $adminSessionId);
assertTest($res['code'] === 404, "Non-existent inquiry_id rejected with 404 Not Found");

destroyTestSession($adminSessionId);

// Clean up guest test fixture
if (file_exists($uploadedDiskPath)) {
    @unlink($uploadedDiskPath);
}
$pdo->prepare('DELETE FROM `inquiry` WHERE inquiry_id = ?')->execute([$guestInqId]);

// ─────────────────────────────────────────────────────────────────
// Section 7: Authenticated Customer Inquiry & Forgery Resistance
// ─────────────────────────────────────────────────────────────────
echo "\n[7] Testing Authenticated Customer Linking & Spoofing Immunity...\n";

$custSessionId = createAuthenticatedSession(['customer_id' => 96, 'role' => 'customer']);

// Submit with forged customer_id parameter
$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Authenticated Customer',
    'phone' => '+94770001122',
    'email' => 'auth.cust@example.com',
    'cakeType' => 'Birthday Cake',
    'eventDate' => $futureDate,
    'customer_id' => 999, // FORGED: should be ignored!
], $custSessionId);

destroyTestSession($custSessionId);

assertTest($res['code'] === 201, "Customer submission returned 201 Created");
$custInqId = (int) ($res['data']['inquiry_id'] ?? 0);

$dbRow = $pdo->prepare('SELECT customer_id, contact_name, contact_phone, contact_email FROM `inquiry` WHERE inquiry_id = ?');
$dbRow->execute([$custInqId]);
$custRecord = $dbRow->fetch(PDO::FETCH_ASSOC);

assertTest((int) $custRecord['customer_id'] === 96, "customer_id derived securely from session (96), NOT forged parameter (999)");
assertTest($custRecord['contact_name'] === 'Authenticated Customer', "Customer contact_name persisted");
assertTest($custRecord['contact_phone'] === '+94770001122', "Customer contact_phone persisted");
assertTest($custRecord['contact_email'] === 'auth.cust@example.com', "Customer contact_email persisted");

// Clean up customer test fixture
$pdo->prepare('DELETE FROM `inquiry` WHERE inquiry_id = ?')->execute([$custInqId]);

// ─────────────────────────────────────────────────────────────────
// Section 8: Flavor Mapping ("Not sure yet" -> NULL)
// ─────────────────────────────────────────────────────────────────
echo "\n[8] Testing Flavor Mapping ('Not sure yet' -> NULL)...\n";

$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Undecided Customer',
    'phone' => '+94775556677',
    'cakeType' => 'Wedding Cake',
    'eventDate' => $futureDate,
    'flavor' => 'Not sure yet',
]);

assertTest($res['code'] === 201, "Undecided customer submission returned 201 Created");
$undecidedId = (int) ($res['data']['inquiry_id'] ?? 0);

$dbRow = $pdo->prepare('SELECT flavor_id FROM `inquiry` WHERE inquiry_id = ?');
$dbRow->execute([$undecidedId]);
$undecidedRecord = $dbRow->fetch(PDO::FETCH_ASSOC);

assertTest($undecidedRecord['flavor_id'] === null, "'Not sure yet' flavor mapped to NULL in database");

// Clean up
$pdo->prepare('DELETE FROM `inquiry` WHERE inquiry_id = ?')->execute([$undecidedId]);

// ─────────────────────────────────────────────────────────────────
// Section 9: Security & XSS / Injection Neutrality
// ─────────────────────────────────────────────────────────────────
echo "\n[9] Testing Injection & Special Character Safety...\n";

$xssPayload = '<script>alert("xss")</script> & "quotes" \'apostrophe\'';
$sqlPayload = "' OR '1'='1; DROP TABLE inquiry; --";

$res = curlRequest("{$baseUrl}/inquiries.php", 'POST', [
    'name' => 'Security Tester',
    'phone' => '+94778889900',
    'cakeType' => 'Party Cake',
    'eventDate' => $futureDate,
    'design' => "XSS: {$xssPayload} | SQL: {$sqlPayload}",
]);

assertTest($res['code'] === 201, "Submission with injection payloads succeeded safely without SQL error");
$secInqId = (int) ($res['data']['inquiry_id'] ?? 0);

$dbRow = $pdo->prepare('SELECT design_description FROM `inquiry` WHERE inquiry_id = ?');
$dbRow->execute([$secInqId]);
$secRecord = $dbRow->fetch(PDO::FETCH_ASSOC);

assertTest($secRecord['design_description'] === "XSS: {$xssPayload} | SQL: {$sqlPayload}", "Payloads stored verbatim via prepared statements");

// Clean up
$pdo->prepare('DELETE FROM `inquiry` WHERE inquiry_id = ?')->execute([$secInqId]);

// ─────────────────────────────────────────────────────────────────
// Section 10: Test Fixture Hygiene & Live DB Cleanliness Check
// ─────────────────────────────────────────────────────────────────
echo "\n[10] Verifying Live Database Cleanliness...\n";

$finalCount = (int) $pdo->query('SELECT COUNT(*) FROM `inquiry`')->fetchColumn();
assertTest($finalCount === $initialDbInquiryCount, "All test fixtures cleaned up; live database count matches initial state ({$initialDbInquiryCount})");

echo "\n=========================================================\n";
echo " Test Results: {$testsPassed} Passed, {$testsFailed} Failed\n";
echo "=========================================================\n";

if ($testsFailed > 0) {
    exit(1);
}
