<?php
require_once __DIR__ . "/../includes/api_bootstrap.php";
require_once '../includes/auth_check.php';
require_once '../includes/helpers.php';


$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$auctionId = (int)($_GET['auction_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$allowedPerPage = [10, 25, 50, 100];
$perPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($perPage, $allowedPerPage)) {
    $perPage = 25;
}
$search = trim($_GET['search'] ?? '');

if (!$auctionId) {
    echo json_encode(['success' => false]);
    exit;
}

// Verify auction belongs to user
$stmt = $db->prepare(
    "SELECT id FROM auction WHERE id=? AND user_id=?"
);
$stmt->execute([$auctionId, $userId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false]);
    exit;
}

// Build search condition
$searchWhere = '';
$searchParams = [];
if ($search !== '') {
    $searchWhere = "AND (
        v.lot LIKE ? OR
        v.make LIKE ? OR
        v.model LIKE ? OR
        m.name LIKE ?
    )";
    $like = '%' . $search . '%';
    $searchParams = [$like, $like, $like, $like];
}

// Count total
$countSql = "
    SELECT COUNT(*)
    FROM vehicles v
    JOIN members m ON v.member_id = m.id
    WHERE v.auction_id = ?
    AND m.user_id = ?
    $searchWhere
";
$countStmt = $db->prepare($countSql);
$countStmt->execute(
    array_merge([$auctionId, $userId], $searchParams)
);
$total = (int)$countStmt->fetchColumn();
$lastPage = max(1, ceil($total / $perPage));
$page = min($page, $lastPage);
$offset = ($page - 1) * $perPage;

// Fetch vehicles for this page
$sql = "
    SELECT
        v.*,
        m.name as member_name
    FROM vehicles v
    JOIN members m ON v.member_id = m.id
    WHERE v.auction_id = ?
    AND m.user_id = ?
    $searchWhere
    ORDER BY v.lot ASC, v.id ASC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($sql);
$stmt->execute(
    array_merge(
        [$auctionId, $userId],
        $searchParams,
        [$perPage, $offset]
    )
);
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'vehicles' => $vehicles,
    'page' => $page,
    'lastPage' => $lastPage,
    'total' => $total,
    'perPage' => $perPage,
    'search' => $search,
]);
