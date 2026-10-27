<?php
/**
 * KúkiCakes — Administrator Inquiry Management API (api/admin-inquiries.php)
 *
 * Provides endpoints for administrators to:
 * - List inquiries with filtering by status and search terms.
 * - Retrieve details of a specific inquiry (including reference image path and design details).
 * - Update inquiry status using authoritative lookup values from `inquiry_status`.
 *
 * Security:
 * - Protected by requireAdmin() (401 for unauthenticated, 403 for customers).
 * - Strict parameter validation and prepared statements.
 */

require_once __DIR__ . '/bootstrap.php';

// Enforce strict administrator authorization
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ─────────────────────────────────────────────────────────────────
// Fetch All Authoritative Statuses
// ─────────────────────────────────────────────────────────────────
try {
    $statusListStmt = $pdo->query('SELECT status_id, status_name FROM `inquiry_status` ORDER BY status_id ASC');
    $allStatuses = $statusListStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    respond(['success' => false, 'message' => 'Database error loading inquiry statuses.'], 500);
}

$statusMapById = [];
$statusMapByName = [];
foreach ($allStatuses as $st) {
    $sid = (int) $st['status_id'];
    $sname = (string) $st['status_name'];
    $statusMapById[$sid] = $sname;
    $statusMapByName[strtolower($sname)] = $sid;
}

// ─────────────────────────────────────────────────────────────────
// GET: List Inquiries or Fetch Single Inquiry Detail
// ─────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $inquiryIdParam = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : null;

    // ── Single Inquiry Detail ─────────────────────────────────────
    if ($inquiryIdParam !== null) {
        try {
            $stmt = $pdo->prepare('
                SELECT 
                    i.inquiry_id,
                    i.customer_id,
                    i.contact_name,
                    i.contact_phone,
                    i.contact_email,
                    i.cake_type,
                    i.event_date,
                    i.no_of_servings,
                    i.budget_range,
                    i.design_description,
                    i.inspiration_image,
                    i.inquiry_date,
                    i.status_id,
                    s.status_name,
                    f.flavor_name,
                    c.full_name AS registered_customer_name,
                    c.email AS registered_customer_email,
                    c.phone_no AS registered_customer_phone
                FROM `inquiry` i
                LEFT JOIN `inquiry_status` s ON i.status_id = s.status_id
                LEFT JOIN `flavor` f ON i.flavor_id = f.flavor_id
                LEFT JOIN `customer` c ON i.customer_id = c.customer_id
                WHERE i.inquiry_id = ?
            ');
            $stmt->execute([$inquiryIdParam]);
            $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$inquiry) {
                respond(['success' => false, 'message' => 'Inquiry not found.'], 404);
            }

            // Clean format
            $inquiry['inquiry_id'] = (int) $inquiry['inquiry_id'];
            $inquiry['customer_id'] = $inquiry['customer_id'] !== null ? (int) $inquiry['customer_id'] : null;
            $inquiry['status_id'] = (int) $inquiry['status_id'];
            $inquiry['no_of_servings'] = $inquiry['no_of_servings'] !== null ? (int) $inquiry['no_of_servings'] : null;
            $inquiry['flavor_name'] = $inquiry['flavor_name'] ?? 'Not Sure Yet';

            // Verify local image path safety (only allow uploads/inquiries/...)
            if ($inquiry['inspiration_image'] !== null) {
                $imgPath = (string) $inquiry['inspiration_image'];
                if (!preg_match('#^uploads/inquiries/inq_[a-f0-9]+\.(jpg|png|webp)$#i', $imgPath)) {
                    $inquiry['inspiration_image'] = null;
                }
            }

            respond([
                'success' => true,
                'inquiry' => $inquiry,
                'statuses' => $allStatuses
            ]);
        } catch (PDOException $e) {
            respond(['success' => false, 'message' => 'Failed to load inquiry details.'], 500);
        }
    }

    // ── List Inquiries with Search and Filtering ──────────────────
    $filterStatus = trim((string) ($_GET['status'] ?? ''));
    $filterStatusId = isset($_GET['status_id']) && is_numeric($_GET['status_id']) ? (int) $_GET['status_id'] : null;
    $search = trim((string) ($_GET['search'] ?? ''));
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 50;
    $offset = isset($_GET['offset']) && is_numeric($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

    $whereClauses = [];
    $params = [];

    // Filter by status
    if ($filterStatusId !== null && isset($statusMapById[$filterStatusId])) {
        $whereClauses[] = 'i.status_id = ?';
        $params[] = $filterStatusId;
    } elseif ($filterStatus !== '') {
        $lowerStatus = strtolower($filterStatus);
        if (isset($statusMapByName[$lowerStatus])) {
            $whereClauses[] = 'i.status_id = ?';
            $params[] = $statusMapByName[$lowerStatus];
        }
    }

    // Filter by search keyword
    if ($search !== '') {
        $whereClauses[] = '(
            i.inquiry_id = ? 
            OR i.contact_name LIKE ? 
            OR i.contact_phone LIKE ? 
            OR i.contact_email LIKE ? 
            OR i.cake_type LIKE ?
            OR c.full_name LIKE ?
        )';
        $searchInt = is_numeric($search) ? (int) $search : -1;
        $searchLike = '%' . $search . '%';
        $params[] = $searchInt;
        $params[] = $searchLike;
        $params[] = $searchLike;
        $params[] = $searchLike;
        $params[] = $searchLike;
        $params[] = $searchLike;
    }

    $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    try {
        // Status counts for filtering UI badges
        $countsStmt = $pdo->query('
            SELECT s.status_id, s.status_name, COUNT(i.inquiry_id) AS cnt
            FROM `inquiry_status` s
            LEFT JOIN `inquiry` i ON s.status_id = i.status_id
            GROUP BY s.status_id, s.status_name
            ORDER BY s.status_id ASC
        ');
        $counts = ['all' => 0];
        foreach ($allStatuses as $st) {
            $counts[$st['status_name']] = 0;
        }
        while ($cRow = $countsStmt->fetch(PDO::FETCH_ASSOC)) {
            $counts[$cRow['status_name']] = (int) $cRow['cnt'];
            $counts['all'] += (int) $cRow['cnt'];
        }

        // Total matching filter
        $totalStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM `inquiry` i 
            LEFT JOIN `customer` c ON i.customer_id = c.customer_id
            $whereSql
        ");
        $totalStmt->execute($params);
        $totalFiltered = (int) $totalStmt->fetchColumn();

        // Inquiries query
        $listSql = "
            SELECT 
                i.inquiry_id,
                i.customer_id,
                i.contact_name,
                i.contact_phone,
                i.contact_email,
                i.cake_type,
                i.event_date,
                i.no_of_servings,
                i.budget_range,
                i.design_description,
                i.inspiration_image,
                i.inquiry_date,
                i.status_id,
                s.status_name,
                f.flavor_name,
                c.full_name AS registered_customer_name
            FROM `inquiry` i
            LEFT JOIN `inquiry_status` s ON i.status_id = s.status_id
            LEFT JOIN `flavor` f ON i.flavor_id = f.flavor_id
            LEFT JOIN `customer` c ON i.customer_id = c.customer_id
            $whereSql
            ORDER BY i.inquiry_id DESC
            LIMIT $limit OFFSET $offset
        ";

        $listStmt = $pdo->prepare($listSql);
        $listStmt->execute($params);
        $rawInquiries = $listStmt->fetchAll(PDO::FETCH_ASSOC);

        $inquiries = array_map(function ($row) {
            $hasImage = !empty($row['inspiration_image']) &&
                preg_match('#^uploads/inquiries/inq_[a-f0-9]+\.(jpg|png|webp)$#i', $row['inspiration_image']);

            return [
                'inquiry_id'         => (int) $row['inquiry_id'],
                'customer_id'        => $row['customer_id'] !== null ? (int) $row['customer_id'] : null,
                'contact_name'       => (string) ($row['contact_name'] ?? $row['registered_customer_name'] ?? 'Guest'),
                'contact_phone'      => (string) ($row['contact_phone'] ?? ''),
                'contact_email'      => (string) ($row['contact_email'] ?? ''),
                'cake_type'          => (string) $row['cake_type'],
                'event_date'         => (string) $row['event_date'],
                'no_of_servings'     => $row['no_of_servings'] !== null ? (int) $row['no_of_servings'] : null,
                'budget_range'       => (string) ($row['budget_range'] ?? 'Not specified'),
                'design_description' => (string) ($row['design_description'] ?? ''),
                'inspiration_image'  => $hasImage ? $row['inspiration_image'] : null,
                'has_image'          => $hasImage,
                'inquiry_date'       => (string) $row['inquiry_date'],
                'status_id'          => (int) $row['status_id'],
                'status_name'        => (string) ($row['status_name'] ?? 'Pending'),
                'flavor_name'        => (string) ($row['flavor_name'] ?? 'Not Sure Yet'),
                'is_registered'      => $row['customer_id'] !== null,
            ];
        }, $rawInquiries);

        respond([
            'success'   => true,
            'inquiries' => $inquiries,
            'counts'    => $counts,
            'statuses'  => $allStatuses,
            'total'     => $totalFiltered,
            'limit'     => $limit,
            'offset'    => $offset,
        ]);

    } catch (PDOException $e) {
        respond(['success' => false, 'message' => 'Failed to load inquiries.'], 500);
    }
}

// ─────────────────────────────────────────────────────────────────
// POST / PUT: Update Inquiry Status
// ─────────────────────────────────────────────────────────────────
if ($method === 'POST' || $method === 'PUT') {
    $body = getBody();
    $action = trim((string) ($body['action'] ?? 'update_status'));

    if ($action !== 'update_status') {
        respond(['success' => false, 'message' => "Unsupported action '{$action}'."], 400);
    }

    $inquiryId = isset($body['inquiry_id']) && is_numeric($body['inquiry_id']) ? (int) $body['inquiry_id'] : null;
    if (!$inquiryId) {
        respond(['success' => false, 'message' => 'Valid inquiry_id is required.'], 400);
    }

    // Resolve requested status
    $targetStatusId = null;
    if (isset($body['status_id']) && is_numeric($body['status_id'])) {
        $candidateId = (int) $body['status_id'];
        if (isset($statusMapById[$candidateId])) {
            $targetStatusId = $candidateId;
        }
    } elseif (isset($body['status_name']) || isset($body['status'])) {
        $candidateName = strtolower(trim((string) ($body['status_name'] ?? $body['status'])));
        if (isset($statusMapByName[$candidateName])) {
            $targetStatusId = $statusMapByName[$candidateName];
        }
    }

    if ($targetStatusId === null) {
        respond([
            'success' => false,
            'message' => 'Invalid status specified. Must match an existing inquiry status.',
            'allowed_statuses' => $allStatuses
        ], 400);
    }

    try {
        $pdo->beginTransaction();

        // Verify inquiry exists and lock row
        $checkStmt = $pdo->prepare('SELECT inquiry_id, status_id FROM `inquiry` WHERE inquiry_id = ? FOR UPDATE');
        $checkStmt->execute([$inquiryId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $pdo->rollBack();
            respond(['success' => false, 'message' => 'Inquiry not found.'], 404);
        }

        $updateStmt = $pdo->prepare('UPDATE `inquiry` SET status_id = ? WHERE inquiry_id = ?');
        $updateStmt->execute([$targetStatusId, $inquiryId]);

        $pdo->commit();

        respond([
            'success'     => true,
            'message'     => 'Inquiry status updated to ' . $statusMapById[$targetStatusId] . '.',
            'inquiry_id'  => $inquiryId,
            'status_id'   => $targetStatusId,
            'status_name' => $statusMapById[$targetStatusId],
        ]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respond(['success' => false, 'message' => 'Database error updating inquiry status.'], 500);
    }
}

respond(['success' => false, 'message' => 'Method Not Allowed.'], 405);
