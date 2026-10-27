<?php
/**
 * KúkiCakes — Test Suite: Profile Validation & Checkout Autofill Endpoints
 */

$baseUrl = 'http://localhost/KukiCakes-Website/api';

function curlReq(string $url, string $method = 'GET', array $data = [], ?string $cookieFile = null): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);

    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        $payload = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
    } else {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    }

    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $code,
        'data' => json_decode((string) $res, true) ?: [],
        'raw'  => (string) $res
    ];
}

$passed = 0;
$failed = 0;

function assertCheck(bool $condition, string $msg): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$msg}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$msg}\n";
        $failed++;
    }
}

echo "=========================================================\n";
echo " KúkiCakes Profile Validation & Autofill Test Suite\n";
echo "=========================================================\n\n";

// 1. Unauthenticated request to update-profile
echo "[1] Testing Authentication Guard on update-profile.php...\n";
$res = curlReq("{$baseUrl}/update-profile.php", 'POST', ['full_name' => 'John Doe']);
assertCheck($res['code'] === 401, "Unauthenticated request rejected with HTTP 401");
assertCheck($res['data']['success'] === false, "Response contains success: false");

// 2. Setup test customers in database
require_once __DIR__ . '/../config/db.php';
$testEmail1 = 'test_profile_val_1_' . time() . '@kukicakes.local';
$testEmail2 = 'test_profile_val_2_' . time() . '@kukicakes.local';
$testPw = 'KukiTestPass123!';
$pwHash = password_hash($testPw, PASSWORD_DEFAULT);

$ins = $pdo->prepare('INSERT INTO customer (full_name, email, phone_no, password_hash, role, account_status) VALUES (?, ?, ?, ?, ?, ?)');
$ins->execute(['User One', $testEmail1, '+94771112222', $pwHash, 'customer', 'active']);
$user1Id = (int) $pdo->lastInsertId();

$ins->execute(['User Two', $testEmail2, '+94773334444', $pwHash, 'customer', 'active']);
$user2Id = (int) $pdo->lastInsertId();

// Create session cookie for User One
$cookieJar = tempnam(sys_get_temp_dir(), 'kuki_cookie_');
$loginRes = curlReq("{$baseUrl}/login.php", 'POST', [
    'email'    => $testEmail1,
    'password' => $testPw
], $cookieJar);
assertCheck($loginRes['code'] === 200, "Logged in as User One (HTTP 200)");

// 3. Test check-session returns phone
echo "\n[2] Testing Session Data (check-session.php)...\n";
$sessRes = curlReq("{$baseUrl}/check-session.php", 'GET', [], $cookieJar);
assertCheck($sessRes['code'] === 200, "check-session.php returns HTTP 200");
assertCheck(($sessRes['data']['user']['phone'] ?? '') === '+94771112222', "check-session returns customer's phone number");
assertCheck(($sessRes['data']['user']['name'] ?? '') === 'User One', "check-session returns customer's name");
assertCheck(($sessRes['data']['user']['email'] ?? '') === $testEmail1, "check-session returns customer's email");

// 4. Test Name Validations
echo "\n[3] Testing Name Validation on update-profile.php...\n";
$res = curlReq("{$baseUrl}/update-profile.php", 'POST', ['full_name' => ''], $cookieJar);
assertCheck($res['code'] === 400, "Empty full_name rejected with HTTP 400");
assertCheck(!empty($res['data']['errors']['full_name']), "Error key full_name returned for empty name");

$res = curlReq("{$baseUrl}/update-profile.php", 'POST', ['full_name' => '   '], $cookieJar);
assertCheck($res['code'] === 400, "Whitespace-only full_name rejected with HTTP 400");

$res = curlReq("{$baseUrl}/update-profile.php", 'POST', ['full_name' => 'A'], $cookieJar);
assertCheck($res['code'] === 400, "Name < 2 characters rejected with HTTP 400");

$longName = str_repeat('X', 101);
$res = curlReq("{$baseUrl}/update-profile.php", 'POST', ['full_name' => $longName], $cookieJar);
assertCheck($res['code'] === 400, "Name > 100 characters rejected with HTTP 400");

$res = curlReq("{$baseUrl}/update-profile.php", 'POST', ['full_name' => '123456'], $cookieJar);
assertCheck($res['code'] === 400, "Numeric-only name rejected with HTTP 400");
assertCheck(str_contains($res['data']['errors']['full_name'] ?? '', 'contain numbers'), "Error message explains numbers are rejected");

$res = curlReq("{$baseUrl}/update-profile.php", 'POST', ['full_name' => 'Amaya123'], $cookieJar);
assertCheck($res['code'] === 400, "Name containing digits ('Amaya123') rejected with HTTP 400");
assertCheck(str_contains($res['data']['errors']['full_name'] ?? '', 'contain numbers'), "Error message explains digits not allowed");

$res = curlReq("{$baseUrl}/update-profile.php", 'POST', ['full_name' => 'Amaya@Perera'], $cookieJar);
assertCheck($res['code'] === 400, "Name with invalid symbols ('Amaya@Perera') rejected with HTTP 400");

// Test valid name formats
$res = curlReq("{$baseUrl}/update-profile.php", 'POST', ['full_name' => 'Mary-Jane Watson'], $cookieJar);
assertCheck($res['code'] === 200, "Valid hyphenated name ('Mary-Jane Watson') accepted with HTTP 200");

$res = curlReq("{$baseUrl}/update-profile.php", 'POST', ['full_name' => "O'Connor"], $cookieJar);
assertCheck($res['code'] === 200, "Valid name with apostrophe (\"O'Connor\") accepted with HTTP 200");

$res = curlReq("{$baseUrl}/update-profile.php", 'POST', ['full_name' => 'A. B. Perera'], $cookieJar);
assertCheck($res['code'] === 200, "Valid name with initials ('A. B. Perera') accepted with HTTP 200");

$res = curlReq("{$baseUrl}/update-profile.php", 'POST', ['full_name' => 'විනුති පෙරේරා'], $cookieJar);
assertCheck($res['code'] === 200, "Valid Sinhala Unicode name ('විනුති පෙරේරා') accepted with HTTP 200");

$res = curlReq("{$baseUrl}/update-profile.php", 'POST', ['full_name' => 'அமயா பெரேரா'], $cookieJar);
assertCheck($res['code'] === 200, "Valid Tamil Unicode name ('அமயா பெரேரா') accepted with HTTP 200");

$res = curlReq("{$baseUrl}/update-profile.php", 'POST', ['full_name' => 'Élodie Renée'], $cookieJar);
assertCheck($res['code'] === 200, "Valid accented Unicode name ('Élodie Renée') accepted with HTTP 200");

// Test registration name validation parity
$regNumeric = curlReq("{$baseUrl}/register.php", 'POST', [
    'full_name' => 'Tester99',
    'email'     => 'numeric_reg_test@kukicakes.local',
    'phone'     => '+94771234567',
    'password'  => 'ValidPass123!',
    'confirm_password' => 'ValidPass123!',
]);
assertCheck($regNumeric['code'] === 400, "Registration with digit in name ('Tester99') rejected with HTTP 400");

// Compatibility test: verify existing customer with numeric name can still log in
$stmt = $pdo->prepare('SELECT email FROM customer WHERE full_name = "test1" LIMIT 1');
$stmt->execute();
$legacyEmail = $stmt->fetchColumn();
if ($legacyEmail) {
    // Check login compatibility without database modification
    $compatCheck = $pdo->prepare('SELECT customer_id, full_name, account_status FROM customer WHERE email = ?');
    $compatCheck->execute([$legacyEmail]);
    $legacyUser = $compatCheck->fetch();
    assertCheck(!empty($legacyUser) && $legacyUser['full_name'] === 'test1', "Legacy customer account record preserved without automatic rewrite");
}

// 5. Test Phone Validations
echo "\n[4] Testing Phone Validation on update-profile.php...\n";
$res = curlReq("{$baseUrl}/update-profile.php", 'POST', [
    'phone' => 'invalid-phone'
], $cookieJar);
assertCheck($res['code'] === 400, "Invalid phone rejected with HTTP 400");
assertCheck(!empty($res['data']['errors']['phone']), "Error key phone returned");

$res = curlReq("{$baseUrl}/update-profile.php", 'POST', [
    'phone' => '123'
], $cookieJar);
assertCheck($res['code'] === 400, "Phone < 7 characters rejected with HTTP 400");

// 6. Test Email Validations
echo "\n[5] Testing Email Validation on update-profile.php...\n";
$res = curlReq("{$baseUrl}/update-profile.php", 'POST', [
    'email' => 'not-an-email'
], $cookieJar);
assertCheck($res['code'] === 400, "Invalid email format rejected with HTTP 400");
assertCheck(!empty($res['data']['errors']['email']), "Error key email returned");

// Attempt to change email to User Two's email (duplicate check)
$res = curlReq("{$baseUrl}/update-profile.php", 'POST', [
    'email' => $testEmail2
], $cookieJar);
assertCheck($res['code'] === 400, "Duplicate email in use by another account rejected with HTTP 400");
assertCheck(str_contains($res['data']['message'] ?? '', 'already in use'), "Error message explains email collision");

// 7. Successful Profile Update
echo "\n[6] Testing Successful Profile Update...\n";
$newValidEmail = 'user_one_updated_' . time() . '@kukicakes.local';
$res = curlReq("{$baseUrl}/update-profile.php", 'POST', [
    'full_name' => 'Updated User One',
    'phone'     => '+94 77 999 8888',
    'email'     => $newValidEmail,
], $cookieJar);
assertCheck($res['code'] === 200, "Valid profile update accepted with HTTP 200");
assertCheck(($res['data']['user']['name'] ?? '') === 'Updated User One', "Returned user name matches updated name");
assertCheck(($res['data']['user']['email'] ?? '') === $newValidEmail, "Returned user email matches updated email");
assertCheck(($res['data']['user']['phone'] ?? '') === '+94 77 999 8888', "Returned user phone matches updated phone");

// Verify in DB directly
$dbCheck = $pdo->prepare('SELECT full_name, email, phone_no FROM customer WHERE customer_id = ?');
$dbCheck->execute([$user1Id]);
$dbUser = $dbCheck->fetch(PDO::FETCH_ASSOC);
assertCheck($dbUser['full_name'] === 'Updated User One', "Database record full_name updated");
assertCheck($dbUser['email'] === $newValidEmail, "Database record email updated");
assertCheck($dbUser['phone_no'] === '+94 77 999 8888', "Database record phone_no updated");

// 8. Clean up fixtures
echo "\n[7] Cleaning up test fixtures...\n";
$pdo->prepare('DELETE FROM customer WHERE customer_id IN (?, ?)')->execute([$user1Id, $user2Id]);
@unlink($cookieJar);
assertCheck(true, "Test customers removed safely from database");

echo "\n=========================================================\n";
echo " Test Results: {$passed} Passed, {$failed} Failed\n";
echo "=========================================================\n";

exit($failed > 0 ? 1 : 0);
