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
$membersPerPage = 10;
$search = trim($_GET['search'] ?? '');

// Sanitize LIKE search
$searchWhere = '';
$searchParams = [];
if ($search !== '' && mb_strlen($search) >= 2) {
    $search = substr($search, 0, 100);
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
    $searchWhere = "AND (
        v.lot LIKE ? OR
        v.make LIKE ? OR
        v.model LIKE ? OR
        m.name LIKE ?
    )";
    $searchParams = [$like, $like, $like, $like];
}

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

// ── MEMBER-BASED PAGINATION ──────────────────────────────────────────
// 1. Get DISTINCT members who have vehicles in this auction
$memberListSql = "
    SELECT DISTINCT m.id, m.name
    FROM members m
    JOIN vehicles v ON v.member_id = m.id
    WHERE v.auction_id = ?
    AND m.user_id = ?
    $searchWhere
    ORDER BY m.name ASC
";
$memberListStmt = $db->prepare($memberListSql);
$memberListStmt->execute(array_merge([$auctionId, $userId], $searchParams));
$allAuctionMembers = $memberListStmt->fetchAll(PDO::FETCH_ASSOC);

$totalMembers = count($allAuctionMembers);
$lastPage = max(1, ceil($totalMembers / $membersPerPage));
$page = min($page, $lastPage);
$offset = ($page - 1) * $membersPerPage;

// 2. Slice the members for THIS page
$pageMembers = array_slice($allAuctionMembers, $offset, $membersPerPage);
$pageMemberIds = array_map(fn($m) => (int)$m['id'], $pageMembers);

// 3. Fetch ALL vehicles for ONLY those members (never split across pages)
if (!empty($pageMemberIds)) {
    $placeholders = implode(',', array_fill(0, count($pageMemberIds), '?'));
    $vehSql = "
        SELECT
            v.*,
            m.name as member_name
        FROM vehicles v
        JOIN members m ON v.member_id = m.id
        WHERE v.auction_id = ?
        AND v.member_id IN ($placeholders)
        ORDER BY m.name ASC, v.lot ASC, v.id ASC
    ";
    $vehStmt = $db->prepare($vehSql);
    $vehStmt->execute(array_merge([$auctionId], $pageMemberIds));
    $vehicles = $vehStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $vehicles = [];
}

// Total vehicles for the badge
$totalVehicles = 0;
$countSql = "SELECT COUNT(*) FROM vehicles v JOIN members m ON v.member_id = m.id WHERE v.auction_id = ? AND m.user_id = ?";
if (!empty($pageMemberIds)) {
    // For the badge, count ALL vehicles in the auction (not just this page)
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM vehicles v JOIN members m ON v.member_id = m.id WHERE v.auction_id = ? AND m.user_id = ?");
    $cntStmt->execute([$auctionId, $userId]);
    $totalVehicles = (int)$cntStmt->fetchColumn();
}

echo json_encode([
    'success' => true,
    'vehicles' => $vehicles,
    'page' => $page,
    'lastPage' => $lastPage,
    'total' => $totalVehicles,
    'totalMembers' => $totalMembers,
    'membersPerPage' => $membersPerPage,
    'pageMembers' => $pageMembers,
    'search' => $search,
]);
